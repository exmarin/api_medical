<?php

class AppointmentController
{
    private $db;
    private $user;

    public function __construct($db, $user)
    {
        $this->db = $db;
        $this->user = $user;
    }

    // Crear una cita
    public function createAppointment($data)
    {
        // Verificar que el rol del usuario sea paciente
        if ($this->user['role'] !== 'paciente') {
            echo json_encode(['message' => 'Solo los pacientes pueden crear citas']);
            return;
        }

        // Verificar que la cita tenga fecha y hora
        if (empty($data->appointment_date) || empty($data->appointment_time)) {
            echo json_encode(['message' => 'Debe proporcionar la fecha y hora de la cita']);
            return;
        }

        // Verificar que la hora esté dentro de las horas permitidas (07:00-12:00 o 14:00-18:00)
        $hour = (int)substr($data->appointment_time, 0, 2);
        if (!($hour >= 7 && $hour < 12) && !($hour >= 14 && $hour < 18)) {
            echo json_encode(['message' => 'La hora debe estar entre 07:00 y 12:00 o entre 14:00 y 18:00']);
            return;
        }

        // Verificar que el paciente no intente pedir una cita con él mismo como doctor
        if ($data->doctor_id == $this->user['id']) {
            echo json_encode(['message' => 'No se puede reservar una cita contigo mismo como doctor']);
            return;
        }

        // Verificar si el doctor existe
        $query = 'SELECT * FROM users WHERE id = :doctor_id AND role = "doctor"';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':doctor_id', $data->doctor_id);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            echo json_encode(['message' => 'El doctor no existe']);
            return;
        }

        // Verificar que la cita no esté ocupada
        $query = 'SELECT * FROM appointments WHERE doctor_id = :doctor_id AND appointment_date = :appointment_date AND appointment_time = :appointment_time';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':doctor_id', $data->doctor_id);
        $stmt->bindParam(':appointment_date', $data->appointment_date);
        $stmt->bindParam(':appointment_time', $data->appointment_time);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            // Si la cita está ocupada, obtener el paciente que tiene la cita
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

            // Obtener el nombre del paciente de la base de datos
            $query_patient = 'SELECT name FROM users WHERE id = :patient_id';
            $stmt_patient = $this->db->prepare($query_patient);
            $stmt_patient->bindParam(':patient_id', $appointment['patient_id']);
            $stmt_patient->execute();
            $patient = $stmt_patient->fetch(PDO::FETCH_ASSOC);

            // Mostrar el mensaje con el nombre del paciente que ya tiene la cita
            echo json_encode(['message' => 'Esta cita ya está ocupada por el paciente ' . $patient['name']]);
            return;
        }

        // Fecha de la cita: convertirla al formato Y-m-d para almacenarla en la base de datos
        $appointment_date = DateTime::createFromFormat('d-m-Y', $data->appointment_date);
        $formatted_date = $appointment_date->format('Y-m-d'); // Almacenamos en formato Y-m-d

        $status = 'pendiente'; // Estado por defecto de la cita

        // Insertar la cita en la base de datos
        $query = 'INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status) 
              VALUES (:patient_id, :doctor_id, :appointment_date, :appointment_time, :status)';
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':patient_id', $this->user['id']);
        $stmt->bindParam(':doctor_id', $data->doctor_id);
        $stmt->bindParam(':appointment_date', $formatted_date); // Usamos la fecha formateada en Y-m-d
        $stmt->bindParam(':appointment_time', $data->appointment_time);
        $stmt->bindParam(':status', $status);

        // Ejecutar la inserción de la cita
        if ($stmt->execute()) {
            // Obtener el nombre del doctor
            $query_doctor = 'SELECT name FROM users WHERE id = :doctor_id';
            $stmt_doctor = $this->db->prepare($query_doctor);
            $stmt_doctor->bindParam(':doctor_id', $data->doctor_id);
            $stmt_doctor->execute();
            $doctor = $stmt_doctor->fetch(PDO::FETCH_ASSOC);

            // Formatear la hora para que se muestre como HH:MM
            $time = substr($data->appointment_time, 0, 5); // Solo la hora y minutos
            $formatted_date = $appointment_date->format('d-m-Y'); // Mostrar la fecha en formato d-m-Y

            // Mostrar el mensaje de éxito con los datos del doctor y la cita
            echo json_encode([
                'message' => 'La cita ha sido creada con éxito con el doctor ' . $doctor['name'] . ' para el día ' . $formatted_date . ' a la hora ' . $time
            ]);
        } else {
            echo json_encode(['message' => 'No se pudo crear la cita']);
        }
    }


    // Ver las citas de un usuario
    public function getAppointments()
    {
        // Consultar las citas programadas para el paciente con el nombre del doctor
        $query = 'SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.appointment_time, a.status, a.created_at, u.name AS doctor_name
              FROM appointments a
              JOIN users u ON a.doctor_id = u.id
              WHERE a.patient_id = :patient_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':patient_id', $this->user['id']);
        $stmt->execute();

        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($appointments) {
            // Modificar el formato de fecha si es necesario y devolver las citas con el nombre del doctor
            foreach ($appointments as &$appointment) {
                $appointment_date = DateTime::createFromFormat('Y-m-d', $appointment['appointment_date']);
                $appointment['appointment_date'] = $appointment_date->format('d-m-Y');
            }
            echo json_encode(['appointments' => $appointments]);
        } else {
            echo json_encode(['message' => 'No tienes citas programadas']);
        }
    }


    // Listar las citas del día (para el médico)
    public function getTodaysAppointments()
    {
        // Obtener la fecha actual
        $today = date('Y-m-d');

        // Consultar las citas programadas para el médico y la fecha actual
        $query = 'SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.appointment_time, a.status, a.created_at, u.name AS patient_name
              FROM appointments a
              JOIN users u ON a.patient_id = u.id
              WHERE a.doctor_id = :doctor_id AND a.appointment_date = :today';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':doctor_id', $this->user['id']);
        $stmt->bindParam(':today', $today);
        $stmt->execute();

        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($appointments) {
            // Modificar el formato de fecha si es necesario y devolver las citas con el nombre del paciente
            foreach ($appointments as &$appointment) {
                $appointment_date = DateTime::createFromFormat('Y-m-d', $appointment['appointment_date']);
                $appointment['appointment_date'] = $appointment_date->format('d-m-Y');
            }
            echo json_encode(['appointments' => $appointments]);
        } else {
            echo json_encode(['message' => 'No tienes citas programadas para hoy']);
        }
    }
}
