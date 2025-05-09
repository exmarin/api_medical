<?php
use PHPUnit\Framework\TestCase;

class AppointmentControllerTest extends TestCase
{
    private $controller;
    private $db;
    private $mockDb;

    // Configuración inicial de la base de datos simulada
    protected function setUp(): void
    {
        // Crear un mock de la base de datos para evitar conexiones reales durante las pruebas
        $this->mockDb = $this->createMock(\PDO::class);
        
        // Configurar el mock para que los métodos prepare y execute devuelvan resultados simulados
        $this->mockDb->method('prepare')->willReturn($this->createMock(\PDOStatement::class));
        
        $this->controller = new AppointmentController($this->mockDb, ['id' => 1, 'role' => 'paciente']);
    }

    // Test para crear una cita
    public function testCreateAppointment()
    {
        // Configurar mocks para simular un doctor existente y una cita creada con éxito
        // Primero, crear un mock que simule la verificación del doctor
        $doctorCheckStatement = $this->createMock(\PDOStatement::class);
        $doctorCheckStatement->method('execute')->willReturn(true);
        $doctorCheckStatement->method('rowCount')->willReturn(1); // Doctor existe
        
        // Luego, crear un mock que simule la verificación de cita ocupada
        $appointmentCheckStatement = $this->createMock(\PDOStatement::class);
        $appointmentCheckStatement->method('execute')->willReturn(true);
        $appointmentCheckStatement->method('rowCount')->willReturn(0); // Cita no ocupada
        
        // Finalmente, crear un mock que simule la inserción de la cita
        $insertStatement = $this->createMock(\PDOStatement::class);
        $insertStatement->method('execute')->willReturn(true);
        
        // Mock para obtener el nombre del doctor
        $doctorNameStatement = $this->createMock(\PDOStatement::class);
        $doctorNameStatement->method('execute')->willReturn(true);
        $doctorNameStatement->method('fetch')->willReturn(['name' => 'Dr. Ejemplo']);
        
        // Configurar el mock de la base de datos para devolver los statements adecuados en secuencia
        $mockDb = $this->createMock(\PDO::class);
        $mockDb->expects($this->exactly(4))
               ->method('prepare')
               ->willReturnOnConsecutiveCalls(
                   $doctorCheckStatement,
                   $appointmentCheckStatement,
                   $insertStatement,
                   $doctorNameStatement
               );
        
        // Crear el controlador con el mock configurado
        $controller = new AppointmentController($mockDb, ['id' => 1, 'role' => 'paciente']);
        
        $data = (object)[
            'appointment_date' => '12-12-2023',
            'appointment_time' => '10:00',
            'doctor_id' => 2
        ];

        // Capturar la salida del método que usa echo
        ob_start();
        $controller->createAppointment($data);
        $response = ob_get_clean();

        // Decodificar la respuesta JSON
        $responseData = json_decode($response, true);
        
        // Verificar que la respuesta contenga el mensaje adecuado
        $this->assertArrayHasKey('message', $responseData);
        $this->assertStringContainsString('La cita ha sido creada con éxito', $responseData['message']);
    }

    // Test para intentar crear una cita con hora fuera del rango permitido
    public function testCreateAppointmentWithInvalidTime()
    {
        $data = (object)[
            'appointment_date' => '12-12-2023',
            'appointment_time' => '06:00',
            'doctor_id' => 2
        ];

        // Capturar la salida del método que usa echo
        ob_start();
        $this->controller->createAppointment($data);
        $response = ob_get_clean();

        // Decodificar la respuesta JSON
        $responseData = json_decode($response, true);
        
        // Verificar que la respuesta contenga el mensaje de error adecuado
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('La hora debe estar entre 07:00 y 12:00 o entre 14:00 y 18:00', $responseData['message']);
    }

    // Test para verificar que un paciente no pueda reservar una cita con él mismo como doctor
    public function testCreateAppointmentWithSelfAsDoctor()
    {
        $data = (object)[
            'appointment_date' => '12-12-2023',
            'appointment_time' => '10:00',
            'doctor_id' => 1 // El paciente está reservando con su propio ID
        ];

        // Capturar la salida del método que usa echo
        ob_start();
        $this->controller->createAppointment($data);
        $response = ob_get_clean();

        // Decodificar la respuesta JSON
        $responseData = json_decode($response, true);
        
        // Verificar que la respuesta contenga el mensaje adecuado
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('No se puede reservar una cita contigo mismo como doctor', $responseData['message']);
    }

    // Test para verificar que una cita ocupada no se pueda reservar
    public function testCreateAppointmentWhenAlreadyOccupied()
    {
        // Configurar el mock para simular una cita ocupada
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->method('execute')->willReturn(true);
        $pdoStatement->method('rowCount')->willReturn(1); // Simular que hay una cita existente
        $pdoStatement->method('fetch')->willReturn(['patient_id' => 3, 'name' => 'Paciente Ejemplo']);
        
        $this->mockDb = $this->createMock(\PDO::class);
        $this->mockDb->method('prepare')->willReturn($pdoStatement);
        
        $this->controller = new AppointmentController($this->mockDb, ['id' => 1, 'role' => 'paciente']);

        $data = (object)[
            'appointment_date' => '12-12-2023',
            'appointment_time' => '10:00',
            'doctor_id' => 2
        ];

        // Capturar la salida del método que usa echo
        ob_start();
        $this->controller->createAppointment($data);
        $response = ob_get_clean();

        // Decodificar la respuesta JSON
        $responseData = json_decode($response, true);
        
        // Verificar que la respuesta contenga el mensaje de que la cita ya está ocupada
        $this->assertArrayHasKey('message', $responseData);
        $this->assertStringContainsString('Esta cita ya está ocupada', $responseData['message']);
    }

    // Test para verificar que una cita puede ser cancelada correctamente
    public function testCancelAppointment()
    {
        // Configurar el mock para simular una cita existente que se puede cancelar
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->method('execute')->willReturn(true);
        $pdoStatement->method('rowCount')->willReturn(1); // Simular que existe la cita
        $pdoStatement->method('fetch')->willReturn([
            'id' => 1,
            'appointment_date' => date('Y-m-d', strtotime('+1 day')) // Fecha futura
        ]);
        
        $this->mockDb = $this->createMock(\PDO::class);
        $this->mockDb->method('prepare')->willReturn($pdoStatement);
        
        $this->controller = new AppointmentController($this->mockDb, ['id' => 1, 'role' => 'paciente']);

        // Simular que el paciente quiere cancelar una cita
        $data = (object)[
            'appointment_date' => date('d-m-Y', strtotime('+1 day')),
            'appointment_time' => '10:00'
        ];

        // Capturar la salida del método que usa echo
        ob_start();
        $this->controller->cancelAppointment($data);
        $response = ob_get_clean();

        // Decodificar la respuesta JSON
        $responseData = json_decode($response, true);
        
        // Verificar que la respuesta indique que la cita fue cancelada
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Cita cancelada con éxito', $responseData['message']);
    }

    // Test para verificar que un paciente no pueda cancelar una cita pasada
    public function testCancelPastAppointment()
    {
        // Configurar el mock para simular una cita pasada
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->method('execute')->willReturn(true);
        $pdoStatement->method('rowCount')->willReturn(1); // Simular que existe la cita
        $pdoStatement->method('fetch')->willReturn([
            'id' => 1,
            'appointment_date' => '2022-01-01' // Fecha pasada
        ]);
        
        $this->mockDb = $this->createMock(\PDO::class);
        $this->mockDb->method('prepare')->willReturn($pdoStatement);
        
        $this->controller = new AppointmentController($this->mockDb, ['id' => 1, 'role' => 'paciente']);

        // Simular que el paciente intenta cancelar una cita pasada
        $data = (object)[
            'appointment_date' => '01-01-2022',
            'appointment_time' => '10:00'
        ];

        // Capturar la salida del método que usa echo
        ob_start();
        $this->controller->cancelAppointment($data);
        $response = ob_get_clean();

        // Decodificar la respuesta JSON
        $responseData = json_decode($response, true);
        
        // Verificar que la respuesta indique que no se puede cancelar una cita pasada
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('No se puede cancelar una cita pasada', $responseData['message']);
    }

    // Test para verificar que solo un doctor pueda confirmar o rechazar citas
    public function testConfirmOrRejectAppointment()
    {
        // Configurar el mock para simular una cita que puede ser confirmada
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->method('execute')->willReturn(true);
        $pdoStatement->method('rowCount')->willReturn(1); // Simular que existe la cita
        $pdoStatement->method('fetch')->willReturn([
            'id' => 1,
            'doctor_id' => 1,
            'payment_status' => 'pagado'
        ]);
        
        $mockDb = $this->createMock(\PDO::class);
        $mockDb->method('prepare')->willReturn($pdoStatement);
        
        $data = (object)[
            'appointment_id' => 1,
            'status' => 'confirmada'
        ];

        // Crear un objeto de doctor
        $user = ['id' => 1, 'role' => 'doctor'];

        // Crear el controlador y pasar el usuario
        $controller = new AppointmentController($mockDb, $user);
        
        // Capturar la salida del método que usa echo
        ob_start();
        $controller->confirmOrRejectAppointment($data);
        $response = ob_get_clean();

        // Decodificar la respuesta JSON
        $responseData = json_decode($response, true);
        
        // Verificar que la respuesta sea de éxito
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Cita confirmada con éxito', $responseData['message']);
    }

    // Test para verificar que solo un doctor pueda rechazar una cita
    public function testRejectAppointment()
    {
        // Configurar el mock para simular una cita que puede ser rechazada
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->method('execute')->willReturn(true);
        $pdoStatement->method('rowCount')->willReturn(1); // Simular que existe la cita
        $pdoStatement->method('fetch')->willReturn([
            'id' => 1,
            'doctor_id' => 1,
            'payment_status' => 'pagado',
            'status' => 'rechazada'
        ]);
        
        $mockDb = $this->createMock(\PDO::class);
        $mockDb->method('prepare')->willReturn($pdoStatement);
        
        $data = (object)[
            'appointment_id' => 1,
            'status' => 'rechazada'
        ];

        // Crear un objeto de doctor
        $user = ['id' => 1, 'role' => 'doctor'];

        // Crear el controlador y pasar el usuario
        $controller = new AppointmentController($mockDb, $user);
        
        // Capturar la salida del método que usa echo
        ob_start();
        $controller->confirmOrRejectAppointment($data);
        $response = ob_get_clean();

        // Decodificar la respuesta JSON
        $responseData = json_decode($response, true);
        
        // Verificar que la respuesta sea de éxito
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Cita rechazada con éxito', $responseData['message']);
    }

    // Test para verificar que el médico no pueda confirmar o rechazar citas que no le pertenecen
    public function testDoctorCannotConfirmOrRejectOtherDoctorsAppointments()
    {
        // Configurar el mock para simular una cita que pertenece a otro doctor
        $pdoStatement = $this->createMock(\PDOStatement::class);
        $pdoStatement->method('execute')->willReturn(true);
        $pdoStatement->method('rowCount')->willReturn(0); // Simular que no existe la cita para este doctor
        $pdoStatement->method('fetch')->willReturn(false);
        
        $mockDb = $this->createMock(\PDO::class);
        $mockDb->method('prepare')->willReturn($pdoStatement);
        
        $data = (object)[
            'appointment_id' => 1,
            'status' => 'confirmada'
        ];

        // Crear un objeto de doctor con ID diferente (ID 2)
        $user = ['id' => 2, 'role' => 'doctor'];

        // Crear el controlador y pasar el usuario
        $controller = new AppointmentController($mockDb, $user);
        
        // Capturar la salida del método que usa echo
        ob_start();
        $controller->confirmOrRejectAppointment($data);
        $response = ob_get_clean();

        // Decodificar la respuesta JSON
        $responseData = json_decode($response, true);
        
        // Verificar que la respuesta indique que el doctor no puede modificar otra cita
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('La cita no existe o no está asociada a este doctor', $responseData['message']);
    }
}
