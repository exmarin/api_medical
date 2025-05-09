<?php

// Incluir el SDK de MercadoPago
require_once __DIR__ . '/../vendor/autoload.php';

// Importar las clases necesarias del SDK de MercadoPago
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;

class PaymentController {
    private $db;
    private $user;

    public function __construct($db, $user) {
        $this->db = $db;
        $this->user = $user;
    }

    public function createPayment($data) {
        // Activar modo de prueba (sin comunicación real con MercadoPago)
        $testMode = true;
        
        // Validar los datos de entrada
        if (empty($data->appointment_id)) {
            echo json_encode(['message' => 'El ID de la cita es requerido']);
            return;
        }

        if (empty($data->amount)) {
            echo json_encode(['message' => 'El monto del pago es requerido']);
            return;
        }

        if (empty($data->token)) {
            echo json_encode(['message' => 'El token de pago es requerido']);
            return;
        }

        if (empty($data->payment_method_id)) {
            echo json_encode(['message' => 'El método de pago es requerido']);
            return;
        }

        // Verificar que la cita exista y pertenezca al usuario
        $query = 'SELECT * FROM appointments WHERE id = :appointment_id AND patient_id = :patient_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':appointment_id', $data->appointment_id);
        $stmt->bindParam(':patient_id', $this->user['id']);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            echo json_encode(['message' => 'La cita no existe o no pertenece a este usuario']);
            return;
        }

        // Verificar que la cita no haya sido pagada ya
        $query = 'SELECT * FROM payments WHERE appointment_id = :appointment_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':appointment_id', $data->appointment_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode(['message' => 'Esta cita ya ha sido pagada']);
            return;
        }

        try {
            // Verificar si estamos en modo de prueba
            if ($testMode) {
                // Simular una respuesta exitosa de MercadoPago
                $paymentResponse = [
                    'id' => 'TEST_' . time(),
                    'status' => 'approved',
                    'status_detail' => 'accredited',
                    'date_approved' => date('Y-m-d\TH:i:s.000\Z'),
                    'payment_method_id' => $data->payment_method_id,
                    'payment_type_id' => 'credit_card',
                    'transaction_amount' => (float)$data->amount
                ];
            } else {
                // Configurar el SDK de MercadoPago con el token de acceso
                $accessToken = 'TEST-4976624280855937-050723-9b6f3771a54ca19da2c882c16eccf8de-204501599';
                MercadoPagoConfig::setAccessToken($accessToken);
                
                // Crear cliente de pago
                $client = new PaymentClient();
                
                // Crear el objeto de pago
                $paymentRequest = [
                    'transaction_amount' => (float)$data->amount,
                    'token' => $data->token,
                    'description' => 'Pago de cita médica',
                    'installments' => 1,
                    'payment_method_id' => $data->payment_method_id,
                    'payer' => [
                        'email' => $this->user['email']
                    ]
                ];
                
                // Procesar el pago
                $payment = $client->create($paymentRequest);
                $paymentResponse = $payment->getResponse()->getContent();
            }
            
            // Si el pago se procesó correctamente
            if (isset($paymentResponse['id'])) {
                // Registrar el pago en la base de datos
                $status = 'pagado';
                $query = 'INSERT INTO payments (appointment_id, amount, status) VALUES (:appointment_id, :amount, :status)';
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':appointment_id', $data->appointment_id);
                $stmt->bindParam(':amount', $data->amount);
                $stmt->bindParam(':status', $status);
                
                if ($stmt->execute()) {
                    // Actualizar el estado de la cita a 'pagada'
                    $appointmentStatus = 'pagada';
                    $query = 'UPDATE appointments SET status = :status WHERE id = :appointment_id';
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(':status', $appointmentStatus);
                    $stmt->bindParam(':appointment_id', $data->appointment_id);
                    $stmt->execute();
                    
                    echo json_encode([
                        'message' => 'Pago procesado con éxito',
                        'payment_id' => $paymentResponse['id'],
                        'status' => $paymentResponse['status']
                    ]);
                } else {
                    echo json_encode(['message' => 'Error al registrar el pago en la base de datos']);
                }
            } else {
                echo json_encode(['message' => 'No se pudo procesar el pago con MercadoPago']);
            }
        } catch (MPApiException $e) {
            // Capturar errores específicos de la API de MercadoPago
            $errorResponse = $e->getApiResponse() ? $e->getApiResponse()->getContent() : [];
            $errorCode = isset($errorResponse['error']) ? $errorResponse['error'] : 'unknown';
            
            echo json_encode([
                'message' => 'Error de MercadoPago: ' . $e->getMessage(),
                'error_code' => $errorCode
            ]);
        } catch (\Exception $e) {
            // Capturar cualquier otro error
            echo json_encode(['message' => 'Error al procesar el pago: ' . $e->getMessage()]);
        }
    }
}