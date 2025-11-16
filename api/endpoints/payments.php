<?php
/**
 * Payments Endpoints
 * CoachSearching.com API
 */

$database = new Database();
$conn = $database->getConnection();

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// POST /payments/create-intent - Create payment intent
if ($route === 'payments/create-intent' && $method === 'POST') {
    $user = requireAuth(['user', 'business']);
    $data = getInputData();

    $errors = validateRequired($data, ['booking_id']);
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }

    // Get booking
    $stmt = $conn->prepare("
        SELECT b.*, cs.price, cs.currency
        FROM bookings b
        INNER JOIN coaching_sessions cs ON b.session_id = cs.id
        WHERE b.id = ? AND b.client_id = ?
    ");
    $stmt->execute([$data['booking_id'], $user['user_id']]);
    $booking = $stmt->fetch();

    if (!$booking) {
        sendError('Booking not found', 404);
    }

    if ($booking['payment_status'] === 'paid') {
        sendError('Booking already paid', 400);
    }

    // Calculate platform fee
    $amount = floatval($booking['total_price']);
    $platformFee = $amount * (PLATFORM_FEE_PERCENTAGE / 100);
    $coachPayout = $amount - $platformFee;

    try {
        // In a real implementation, you would integrate with Stripe or another payment provider
        // For now, we'll just create a transaction record

        $stmt = $conn->prepare("
            INSERT INTO payment_transactions (
                booking_id, coach_id, client_id, amount, currency,
                platform_fee, coach_payout, payment_method, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");

        $stmt->execute([
            $data['booking_id'],
            $booking['coach_id'],
            $user['user_id'],
            $amount,
            $booking['currency'],
            $platformFee,
            $coachPayout,
            'stripe'
        ]);

        $transactionId = $conn->lastInsertId();

        // TODO: Create actual Stripe payment intent
        // $stripe = new \Stripe\StripeClient($_ENV['STRIPE_SECRET_KEY']);
        // $paymentIntent = $stripe->paymentIntents->create([...]);

        sendResponse([
            'message' => 'Payment intent created',
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $booking['currency'],
            // 'client_secret' => $paymentIntent->client_secret
        ], 201);

    } catch (PDOException $e) {
        error_log("Payment intent error: " . $e->getMessage());
        sendError('Failed to create payment intent', 500);
    }
}

// POST /payments/webhook - Handle payment webhook (Stripe)
if ($route === 'payments/webhook' && $method === 'POST') {
    // Get the webhook payload
    $payload = file_get_contents('php://input');
    $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    // TODO: Verify webhook signature with Stripe
    // This is a simplified version - in production, verify the signature

    $event = json_decode($payload, true);

    if ($event['type'] === 'payment_intent.succeeded') {
        $paymentIntent = $event['data']['object'];
        $transactionId = $paymentIntent['metadata']['transaction_id'] ?? null;

        if ($transactionId) {
            try {
                $conn->beginTransaction();

                // Update transaction status
                $stmt = $conn->prepare("
                    UPDATE payment_transactions
                    SET status = 'completed', provider_transaction_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$paymentIntent['id'], $transactionId]);

                // Get transaction
                $stmt = $conn->prepare("SELECT * FROM payment_transactions WHERE id = ?");
                $stmt->execute([$transactionId]);
                $transaction = $stmt->fetch();

                if ($transaction) {
                    // Update booking payment status
                    $stmt = $conn->prepare("
                        UPDATE bookings
                        SET payment_status = 'paid', payment_id = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$paymentIntent['id'], $transaction['booking_id']]);

                    // Send notification to coach
                    sendNotification(
                        $conn,
                        $transaction['coach_id'],
                        'payment',
                        'Payment Received',
                        'You received a payment',
                        "/coach/bookings/{$transaction['booking_id']}"
                    );
                }

                $conn->commit();
            } catch (PDOException $e) {
                $conn->rollBack();
                error_log("Webhook processing error: " . $e->getMessage());
            }
        }
    }

    http_response_code(200);
    echo json_encode(['received' => true]);
    exit();
}

// If no route matched
sendError('Invalid endpoint or method', 404);
