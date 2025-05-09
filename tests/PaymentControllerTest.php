<?php
use PHPUnit\Framework\TestCase;

class PaymentControllerTest extends TestCase
{
    private $mockDb;
    private $controller;

    protected function setUp(): void
    {
        // Crear un mock de la base de datos para evitar conexiones reales durante las pruebas
        $this->mockDb = $this->createMock(\PDO::class);
        
        // Usuario de prueba
        $user = [
            'id' => 1,
            'email' => 'paciente@example.com',
            'role' => 'paciente'
        ];
        
        // Crear el controlador con el mock de la base de datos
        $this->controller = new PaymentController($this->mockDb, $user);
    }

    public function testCreatePaymentWithMissingAppointmentId()
    {
        $data = (object)[
            'amount' => 100.00,
            'token' => 'token_test',
            'payment_method_id' => 'visa'
            // Falta appointment_id
        ];

        // Capturar la salida del método
        ob_start();
        $this->controller->createPayment($data);
        $response = ob_get_clean();

        // Decodificar la respuesta JSON
        $responseData = json_decode($response, true);
        
        // Verificar que la respuesta contenga el mensaje de error adecuado
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('El ID de la cita es requerido', $responseData['message']);
    }

    public function testCreatePaymentWithMissingAmount()
    {
        $data = (object)[
            'appointment_id' => 1,
            // Falta amount
            'token' => 'token_test',
            'payment_method_id' => 'visa'
        ];

        // Capturar la salida del método
        ob_start();
        $this->controller->createPayment($data);
        $response = ob_get_clean();

        // Decodificar la respuesta JSON
        $responseData = json_decode($response, true);
        
        // Verificar que la respuesta contenga el mensaje de error adecuado
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('El monto del pago es requerido', $responseData['message']);
    }

    public function testCreatePaymentWithNonExistentAppointment()
    {
        // Configurar el mock para simular que la cita no existe
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->method('execute')->willReturn(true);
        $pdoStatement->method('rowCount')->willReturn(0); // No hay citas que coincidan
        
        $this->mockDb->method('prepare')->willReturn($pdoStatement);
        
        $data = (object)[
            'appointment_id' => 999, // ID que no existe
            'amount' => 100.00,
            'token' => 'token_test',
            'payment_method_id' => 'visa'
        ];

        // Capturar la salida del método
        ob_start();
        $this->controller->createPayment($data);
        $response = ob_get_clean();

        // Decodificar la respuesta JSON
        $responseData = json_decode($response, true);
        
        // Verificar que la respuesta contenga el mensaje de error adecuado
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('La cita no existe o no pertenece a este usuario', $responseData['message']);
    }

    public function testCreatePaymentForAlreadyPaidAppointment()
    {
        // Configurar el mock para simular que la cita existe
        $appointmentStatement = $this->createMock(\PDOStatement::class);
        $appointmentStatement->method('execute')->willReturn(true);
        $appointmentStatement->method('rowCount')->willReturn(1); // La cita existe
        
        // Configurar el mock para simular que el pago ya existe
        $paymentStatement = $this->createMock(\PDOStatement::class);
        $paymentStatement->method('execute')->willReturn(true);
        $paymentStatement->method('rowCount')->willReturn(1); // Ya hay un pago para esta cita
        
        // Configurar el mock de la base de datos para devolver los statements adecuados en secuencia
        $this->mockDb->expects($this->exactly(2))
               ->method('prepare')
               ->willReturnOnConsecutiveCalls(
                   $appointmentStatement,
                   $paymentStatement
               );
        
        $data = (object)[
            'appointment_id' => 1,
            'amount' => 100.00,
            'token' => 'token_test',
            'payment_method_id' => 'visa'
        ];

        // Capturar la salida del método
        ob_start();
        $this->controller->createPayment($data);
        $response = ob_get_clean();

        // Decodificar la respuesta JSON
        $responseData = json_decode($response, true);
        
        // Verificar que la respuesta contenga el mensaje de error adecuado
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Esta cita ya ha sido pagada', $responseData['message']);
    }

    // Nota: No podemos probar completamente la integración con MercadoPago en pruebas unitarias
    // ya que requeriría una conexión real. Para eso se necesitarían pruebas de integración.
}
