<?php
// ---------- Settings ----------
define('API_KEY', 'pg123secret');
define('DATA_FILE', __DIR__ . '/tenants.data.php');
define('PAYMENTS_FILE', __DIR__ . '/payments.data.php');
define('GUARD', "<?php http_response_code(403); exit; ?>\n");

// ---------- Headers (JSON response & CORS) ----------
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

// Browser preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// ---------- API key check ----------
$key = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!hash_equals(API_KEY, $key)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or missing API key']);
    exit;
}

// ---------- Helper functions ----------
function readJsonFile($filePath) {
    if (!file_exists($filePath)) return [];
    $raw = @file_get_contents($filePath);
    if ($raw === false) return [];
    if (str_starts_with($raw, GUARD)) {
        $raw = substr($raw, strlen(GUARD));
    }
    return json_decode($raw, true) ?: [];
}

function writeJsonFile($filePath, $data) {
    $content = GUARD . json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($filePath, $content, LOCK_EX);
}

function respond($code, $body) {
    http_response_code($code);
    echo json_encode($body);
    exit;
}

// ---------- Request info ----------
$method   = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? 'tenants';
$id       = isset($_GET['id']) ? (int)$_GET['id'] : null;
$input    = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];

$tenants  = readJsonFile(DATA_FILE);
$payments = readJsonFile(PAYMENTS_FILE);

// ==========================================
// 1. DASHBOARD ANALYTICS RESOURCE
// ==========================================
if ($resource === 'stats') {
    if ($method !== 'GET') respond(405, ['error' => 'Method not allowed']);

    $targetMonth = $_GET['month'] ?? date('Y-m');
    $totalTenants = count($tenants);
    $totalRentExpected = 0;
    $occupiedRooms = [];

    // Calculate payments per tenant for the selected month
    $paidByTenant = [];
    $monthCollected = 0;

    foreach ($payments as $p) {
        if (($p['month'] ?? '') === $targetMonth) {
            $tId = (int)($p['tenant_id'] ?? 0);
            $amt = (int)($p['amount'] ?? 0);
            $paidByTenant[$tId] = ($paidByTenant[$tId] ?? 0) + $amt;
            $monthCollected += $amt;
        }
    }

    $fullyPaidCount = 0;
    $partialCount   = 0;
    $unpaidCount    = 0;
    $advanceCount   = 0;
    $totalPendingRent = 0;

    foreach ($tenants as $t) {
        $rent = (int)($t['rent'] ?? 0);
        $totalRentExpected += $rent;
        if (!empty($t['room'])) {
            $occupiedRooms[$t['room']] = true;
        }

        $paid = $paidByTenant[$t['id']] ?? 0;
        if ($paid === 0) {
            $unpaidCount++;
            $totalPendingRent += $rent;
        } elseif ($paid < $rent) {
            $partialCount++;
            $totalPendingRent += ($rent - $paid);
        } elseif ($paid === $rent) {
            $fullyPaidCount++;
        } else {
            $advanceCount++;
        }
    }

    respond(200, [
        'month'               => $targetMonth,
        'total_tenants'       => $totalTenants,
        'occupied_rooms'      => count($occupiedRooms),
        'expected_rent'       => $totalRentExpected,
        'collected_rent'      => $monthCollected,
        'pending_rent'        => $totalPendingRent,
        'fully_paid_count'    => $fullyPaidCount,
        'partial_count'       => $partialCount,
        'unpaid_count'        => $unpaidCount,
        'advance_count'       => $advanceCount,
        'total_transactions'  => count($payments)
    ]);
}

// ==========================================
// 2. PAYMENTS RESOURCE
// ==========================================
if ($resource === 'payments') {
    switch ($method) {
        case 'GET':
            $filterTenantId = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null;
            $filterMonth    = $_GET['month'] ?? null;

            $result = $payments;
            if ($filterTenantId !== null) {
                $result = array_filter($result, fn($p) => ($p['tenant_id'] ?? 0) === $filterTenantId);
            }
            if ($filterMonth !== null && $filterMonth !== '') {
                $result = array_filter($result, fn($p) => ($p['month'] ?? '') === $filterMonth);
            }

            usort($result, fn($a, $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));
            respond(200, array_values($result));
            break;

        case 'POST':
            $tenantId = (int)($input['tenant_id'] ?? 0);
            $amount   = (int)($input['amount'] ?? 0);
            $month    = trim($input['month'] ?? date('Y-m'));
            $date     = trim($input['date'] ?? date('Y-m-d'));
            $mode     = trim($input['mode'] ?? 'UPI');
            $note     = trim($input['note'] ?? '');

            if ($tenantId <= 0 || $amount <= 0) {
                respond(400, ['error' => 'Valid tenant_id and amount greater than 0 are required']);
            }

            // Find tenant details
            $targetTenant = null;
            $tenantIndex  = null;
            foreach ($tenants as $idx => $t) {
                if ($t['id'] === $tenantId) {
                    $targetTenant = $t;
                    $tenantIndex = $idx;
                    break;
                }
            }

            if (!$targetTenant) {
                respond(404, ['error' => 'Associated tenant not found']);
            }

            // Calculate total paid so far in this month including this payment
            $alreadyPaid = 0;
            foreach ($payments as $p) {
                if (($p['tenant_id'] ?? 0) === $tenantId && ($p['month'] ?? '') === $month) {
                    $alreadyPaid += (int)($p['amount'] ?? 0);
                }
            }
            $newTotalPaid = $alreadyPaid + $amount;
            $tenantRent   = (int)($targetTenant['rent'] ?? 0);

            // Determine status: Paid, Partial, or Advance
            if ($newTotalPaid >= $tenantRent) {
                $calculatedStatus = ($newTotalPaid > $tenantRent) ? 'Advance' : 'Paid';
            } else {
                $calculatedStatus = 'Partial';
            }

            // Generate new payment ID & Receipt Number
            $newPaymentId = 1;
            foreach ($payments as $p) {
                if (($p['id'] ?? 0) >= $newPaymentId) $newPaymentId = $p['id'] + 1;
            }

            $cleanMonthStr = str_replace('-', '', $month);
            $receiptNo = 'RCP-' . $cleanMonthStr . '-' . str_pad((string)$newPaymentId, 4, '0', STR_PAD_LEFT);

            $payment = [
                'id'              => $newPaymentId,
                'tenant_id'       => $tenantId,
                'tenant_name'     => $targetTenant['name'],
                'room'            => $targetTenant['room'],
                'phone'           => $targetTenant['phone'] ?? '',
                'month'           => $month,
                'amount'          => $amount,
                'date'            => $date,
                'mode'            => in_array($mode, ['UPI', 'Cash', 'Bank Transfer', 'Cheque']) ? $mode : 'UPI',
                'receipt_no'      => $receiptNo,
                'note'            => $note,
                'total_paid'      => $newTotalPaid,
                'rent'            => $tenantRent,
                'balance'         => max(0, $tenantRent - $newTotalPaid),
                'advance'         => max(0, $newTotalPaid - $tenantRent),
                'payment_status'  => $calculatedStatus
            ];

            $payments[] = $payment;
            writeJsonFile(PAYMENTS_FILE, $payments);

            // Update tenant's current status in tenants.data.php
            if ($tenantIndex !== null) {
                $tenants[$tenantIndex]['status'] = $calculatedStatus;
                writeJsonFile(DATA_FILE, $tenants);
            }

            respond(201, $payment);
            break;

        case 'DELETE':
            if ($id === null) respond(400, ['error' => 'id is required']);
            $found = false;
            foreach ($payments as $i => $p) {
                if ($p['id'] === $id) {
                    unset($payments[$i]);
                    $found = true;
                    break;
                }
            }
            if (!$found) respond(404, ['error' => 'Payment record not found']);
            writeJsonFile(PAYMENTS_FILE, $payments);
            respond(200, ['message' => 'Payment record deleted successfully']);
            break;

        default:
            respond(405, ['error' => 'Method not allowed']);
    }
}

// ==========================================
// 3. TENANTS RESOURCE (DEFAULT)
// ==========================================
switch ($method) {
    case 'GET':
        if ($id === null) {
            respond(200, $tenants);
        }
        foreach ($tenants as $t) {
            if ($t['id'] === $id) respond(200, $t);
        }
        respond(404, ['error' => 'Tenant not found']);
        break;

    case 'POST':
        if (trim($input['name'] ?? '') === '' || trim($input['room'] ?? '') === '') {
            respond(400, ['error' => 'name and room are required']);
        }
        $newId = 1;
        foreach ($tenants as $t) {
            if ($t['id'] >= $newId) $newId = $t['id'] + 1;
        }
        $tenant = [
            'id'        => $newId,
            'name'      => trim($input['name']),
            'phone'     => trim($input['phone'] ?? ''),
            'room'      => trim($input['room']),
            'rent'      => (int)($input['rent'] ?? 0),
            'join_date' => $input['join_date'] ?? date('Y-m-d'),
            'status'    => $input['status'] ?? 'Pending',
        ];
        $tenants[] = $tenant;
        writeJsonFile(DATA_FILE, $tenants);
        respond(201, $tenant);
        break;

    case 'PUT':
        if ($id === null) respond(400, ['error' => 'id is required']);
        foreach ($tenants as $i => $t) {
            if ($t['id'] === $id) {
                $tenants[$i]['name']      = trim($input['name']      ?? $t['name']);
                $tenants[$i]['phone']     = trim($input['phone']     ?? $t['phone']);
                $tenants[$i]['room']      = trim($input['room']      ?? $t['room']);
                $tenants[$i]['rent']      = (int)($input['rent']     ?? $t['rent']);
                $tenants[$i]['join_date'] = $input['join_date']      ?? $t['join_date'];
                $tenants[$i]['status']    = $input['status']         ?? $t['status'];
                writeJsonFile(DATA_FILE, $tenants);
                respond(200, $tenants[$i]);
            }
        }
        respond(404, ['error' => 'Tenant not found']);
        break;

    case 'DELETE':
        if ($id === null) respond(400, ['error' => 'id is required']);
        $deleted = false;
        foreach ($tenants as $i => $t) {
            if ($t['id'] === $id) {
                unset($tenants[$i]);
                $deleted = true;
                break;
            }
        }
        if (!$deleted) respond(404, ['error' => 'Tenant not found']);
        writeJsonFile(DATA_FILE, $tenants);
        respond(200, ['message' => 'Tenant deleted']);
        break;

    default:
        respond(405, ['error' => 'Method not allowed']);
}
