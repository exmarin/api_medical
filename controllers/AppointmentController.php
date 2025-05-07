<?php

class AppointmentController {
    private $db;
    private $user;

    public function __construct($db, $user) {
        $this->db = $db;
        $this->user = $user;
    }

    // Crear una cita
    public function createAppointment($data) {
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

        // Verificar que la cita no esté ocupada
        $query = 'SELECT * FROM appointments WHERE doctor_id = :doctor_id AND appointment_date = :appointment_date AND appointment_time = :appointment_time';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':doctor_id', $data->doctor_id);
        $stmt->bindParam(':appointment_date', $data->appointment_date);
        $stmt->bindParam(':appointment_time', $data->appointment_time);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode(['message' => 'Esta cita ya está ocupada']);
            return;
        }

        // Verificar si el paciente está registrado
        $query = 'SELECT * FROM users WHERE id = :patient_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':patient_id', $this->user['id']);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            echo json_encode(['message' => 'Paciente no registrado']);
            return;
        }

        $status = 'pendiente'; // Estado por defecto de la cita
        
        // Insertar la cita en la base de datos 
        $query = 'INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status) VALUES (:patient_id, :doctor_id, :appointment_date, :appointment_time, :status)';
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':patient_id', $this->user['id']);
        $stmt->bindParam(':doctor_id', $data->doctor_id);
        $stmt->bindParam(':appointment_date', $data->appointment_date);
        $stmt->bindParam(':appointment_time', $data->appointment_time);
        $stmt->bindParam(':status', $status);

        if ($stmt->execute()) {
            echo json_encode(['message' => 'La cita ha sido creada con éxito']);
        } else {
            echo json_encode(['message' => 'No se pudo crear la cita']);
        }
    }

    // Ver las citas de un usuario
    public function getAppointments() {
        $query = 'SELECT * FROM appointments WHERE patient_id = :patient_id';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':patient_id', $this->user['id']);
        $stmt->execute();

        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($appointments) {
            echo json_encode(['appointments' => $appointments]);
        } else {
            echo json_encode(['message' => 'No tienes citas programadas']);
        }
    }
}