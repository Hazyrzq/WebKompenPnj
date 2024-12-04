<?php
// payment_receipt_helper.php

function ensureReceiptExists($paymentId, $pdo = null) {
    if (!$pdo) {
        $pdo = connectDB();
    }
    
    try {
        // Check if receipt exists
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM TBL_PAYMENT_REPORTS pr
            WHERE pr.PAYMENT_ID = :payment_id
        ");
        $stmt->execute(['payment_id' => $paymentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['COUNT'] == 0) {
            // Receipt doesn't exist, generate it
            require_once 'payment_receipt.php';
            generatePaymentReceipt($paymentId);
            
            // Verify receipt was created
            $stmt->execute(['payment_id' => $paymentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['COUNT'] == 0) {
                throw new Exception("Failed to generate receipt");
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error ensuring receipt exists: " . $e->getMessage());
        throw $e;
    }
}

function generateReceiptSafely($paymentId) {
    try {
        $pdo = connectDB();
        
        // Get payment status first
        $stmt = $pdo->prepare("
            SELECT STATUS, ORDER_ID 
            FROM TBL_PAYMENTS 
            WHERE PAYMENT_ID = :payment_id
        ");
        $stmt->execute(['payment_id' => $paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            throw new Exception("Payment not found");
        }
        
        if ($payment['STATUS'] !== 'success') {
            throw new Exception("Cannot generate receipt for non-successful payment");
        }
        
        return ensureReceiptExists($paymentId, $pdo);
    } catch (Exception $e) {
        error_log("Error in generateReceiptSafely: " . $e->getMessage());
        throw $e;
    }
}