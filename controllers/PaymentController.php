<?php

require_once __DIR__ . '/vendor/autoload.php';
use MercadoPago\SDK;
use MercadoPago\Resources\Payment;



class PaymentController {
    private $db;
    private $user;

    public function __construct($db, $user) {
        $this->db = $db;
        $this->user = $user;
    }

    public function createPayment($data) {
        // 1. Configurar el SDK
        SDK::setAccessToken('TEST-4976624280855937-050723-9b6f3771a54ca19da2c882c16eccf8de-204501599'); // Usa tu token real
        
        // 2. Crear el objeto de pago
        $payment = new Payment();
        
        // 3. Configurar los datos del pago
        $payment->transaction_amount = (float)$data->amount;
        $payment->token = $data->token;
        $payment->description = "Pago de cita médica";
        $payment->payment_method_id = $data->payment_method_id;
        $payment->payer = [
            "email" => $this->user["email"]
        ];
        
        // 4. Intentar procesar el pago
        try {
            if ($payment->save()) {
                return [
                    'success' => true,
                    'payment_id' => $payment->id,
                    'status' => $payment->status
                ];
            }
            return ['success' => false, 'error' => 'No se pudo guardar el pago'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}