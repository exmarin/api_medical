# API Medical - Sistema de Citas Médicas

## Descripción General

La API Medical es una API RESTful que permite a los pacientes crear y gestionar citas médicas, y a los médicos ver las citas programadas para ellos. Utiliza MercadoPago para gestionar los pagos de las citas. La API permite realizar acciones como registrar usuarios, iniciar sesión, crear citas y más.

Esta API está construida en PHP con MySQL como base de datos y utiliza MercadoPago para los pagos. Está pensada para ser usada en el entorno sandbox de MercadoPago, para que puedas probar las funcionalidades sin realizar pagos reales.

## Tecnologías Utilizadas

- **PHP**: Lenguaje de programación del backend.
- **MySQL**: Base de datos para almacenar la información de los usuarios, citas y pagos.
- **MercadoPago SDK**: Para la integración con el sistema de pagos.
- **Composer**: Para la gestión de dependencias.
- **PDO**: Para conexiones seguras a la base de datos.
- **Postman**: Para realizar pruebas y consumir la API.

## Requisitos de Instalación

1. PHP 7.4 o superior
2. MySQL 5.7 o superior
3. Servidor web (Apache, Nginx)
4. Composer: Para instalar dependencias
5. Extensión PDO de PHP habilitada
6. Cuenta de MercadoPago: Necesitarás una cuenta para obtener tu token de acceso

## Instalación

1. **Clona el repositorio**

```bash
git clone https://github.com/exmarin/api_medical
```

2. **Instala las dependencias**

Navega hasta el directorio raíz del proyecto e instala las dependencias con Composer:

```bash
cd api_medical
composer install
```

3. **Configura la base de datos**

El proyecto incluye un archivo `scripts/create_databse.sql` que contiene el script necesario para crear las tablas en tu base de datos MySQL.

Importa el script a tu base de datos:

Puedes ejecutar este script desde tu herramienta de administración de base de datos, como phpMyAdmin o la terminal MySQL.

```sql
source path_to_script/create_databse.sql;
```

4. **Configura MercadoPago**

En el archivo `controllers/PaymentController.php`, reemplaza el token de acceso por tu Access Token real de MercadoPago, obteniéndolo desde el panel de desarrolladores de MercadoPago.

```php
SDK::setAccessToken('YOUR_ACCESS_TOKEN'); // Sustituye con tu Access Token real
```



## Estructura de la Base de Datos

La API utiliza tres tablas principales:

### Tabla `users`
- `id`: Identificador único del usuario (AUTO_INCREMENT)
- `name`: Nombre del usuario
- `email`: Correo electrónico (único)
- `password`: Contraseña (almacenada con hash)
- `role`: Rol del usuario (paciente, medico, doctor)
- `token`: Token de autenticación
- `created_at`: Fecha de creación del registro

### Tabla `appointments`
- `id`: Identificador único de la cita (AUTO_INCREMENT)
- `patient_id`: ID del paciente (referencia a users.id)
- `doctor_id`: ID del doctor (referencia a users.id)
- `appointment_date`: Fecha de la cita (formato Y-m-d)
- `appointment_time`: Hora de la cita
- `status`: Estado de la cita (pendiente, pagada, confirmada, rechazada)
- `created_at`: Fecha de creación del registro

### Tabla `payments`
- `id`: Identificador único del pago (AUTO_INCREMENT)
- `appointment_id`: ID de la cita (referencia a appointments.id)
- `amount`: Monto del pago
- `status`: Estado del pago (pagado)
- `payment_date`: Fecha del pago

## Endpoints de la API

### Autenticación

#### Registro de Usuario
- **URL**: `/register`
- **Método**: `POST`
- **Descripción**: Registra un nuevo usuario en el sistema (paciente o doctor)
- **Parámetros de Entrada**:
  ```json
  {
    "name": "Carmen Moraga",
    "email": "carmen.moraga@example.com",
    "password": "Carmen.2025",
    "role": "paciente"
  }
  ```
- **Respuestas**:
  - 200 OK: `{"message": "Usuario registrado con éxito"}`
  - 400 Bad Request: Error al registrar el usuario (Ej: Email ya registrado, falta de campos obligatorios)

#### Login de Usuario
- **URL**: `/login`
- **Método**: `POST`
- **Descripción**: Permite a un usuario autenticarse y obtener un token de acceso
- **Parámetros de Entrada**:
  ```json
  {
    "email": "carmen.moraga@example.com",
    "password": "Carmen.2025"
  }
  ```
- **Respuesta Exitosa**:
  ```json
  {
    "message": "Login successful",
    "user": {
      "id": 1,
      "name": "Carmen Moraga",
      "email": "carmen.moraga@example.com",
      "role": "paciente",
      "token": "token_generado"
    }
  }
  ```
- **Respuestas de Error**:
  - Usuario no encontrado: `{"message": "Usuario no encontrado"}`
  - Contraseña incorrecta: `{"message": "Contraseña incorrecta"}`

### Gestión de Citas

#### Crear Cita
- **URL**: `/appointments?token=token_usuario`
- **Método**: `POST`
- **Descripción**: Permite a un paciente crear una nueva cita médica
- **Autenticación**: Token de usuario requerido
- **Parámetros de Entrada**:
  ```json
  {
    "doctor_id": 7,
    "appointment_date": "08-05-2025",
    "appointment_time": "14:30"
  }
  ```
- **Restricciones**:
  - La hora debe estar entre 07:00-12:00 o 14:00-18:00
  - No se puede reservar una cita con uno mismo como doctor
  - No se puede reservar una cita ya ocupada
- **Respuesta Exitosa**:
  ```json
  {
    "message": "La cita ha sido creada con éxito con el doctor [nombre_doctor] para el día 08-05-2025 a la hora 14:30"
  }
  ```
- **Respuestas de Error**:
  - Rol incorrecto: `{"message": "Solo los pacientes pueden crear citas"}`
  - Hora inválida: `{"message": "La hora debe estar entre 07:00 y 12:00 o entre 14:00 y 18:00"}`
  - Cita ocupada: `{"message": "Esta cita ya está ocupada por el paciente [nombre_paciente]"}`

#### Ver Citas de Usuario
- **URL**: `/appointments?token=token_usuario`
- **Método**: `GET`
- **Descripción**: Permite a un paciente ver sus citas programadas
- **Autenticación**: Token de usuario requerido
- **Respuesta Exitosa**:
  ```json
  {
    "appointments": [
      {
        "id": 1,
        "patient_id": 2,
        "doctor_id": 3,
        "appointment_date": "15-05-2025",
        "appointment_time": "10:30:00",
        "status": "pendiente",
        "created_at": "2025-05-08 20:30:45",
        "doctor_name": "Dr. Ejemplo"
      }
    ]
  }
  ```
- **Respuesta sin Citas**:
  ```json
  {
    "message": "No tienes citas programadas"
  }
  ```

#### Ver Citas del Día (para médicos)
- **URL**: `/todays_appointments?token=token_doctor`
- **Método**: `GET`
- **Descripción**: Lista las citas programadas para el día actual para el médico autenticado
- **Autenticación**: Token de usuario (médico) requerido
- **Respuesta Exitosa**:
  ```json
  {
    "appointments": [
      {
        "id": 1,
        "patient_id": 2,
        "doctor_id": 3,
        "appointment_date": "08-05-2025",
        "appointment_time": "10:30:00",
        "status": "pendiente",
        "created_at": "2025-05-08 08:30:45",
        "patient_name": "Paciente Ejemplo"
      }
    ]
  }
  ```
- **Respuesta sin Citas**:
  ```json
  {
    "message": "No tienes citas programadas para hoy"
  }
  ```

#### Confirmar o Rechazar Cita
- **URL**: `/appointments/confirmOrReject?token=token_doctor`
- **Método**: `POST`
- **Descripción**: Permite al médico confirmar o rechazar una cita
- **Autenticación**: Token de usuario (médico) requerido
- **Parámetros de Entrada**:
  ```json
  {
    "appointment_id": 1,
    "status": "confirmada"
  }
  ```
- **Restricciones**:
  - Solo los médicos pueden confirmar o rechazar citas
  - La cita debe estar pagada para poder ser confirmada
  - El estado debe ser "confirmada" o "rechazada"
- **Respuesta Exitosa**:
  ```json
  {
    "message": "Cita confirmada con éxito"
  }
  ```

#### Cancelar Cita
- **URL**: `/appointments/cancel?token=token_paciente`
- **Método**: `POST`
- **Descripción**: Permite al paciente cancelar una cita
- **Autenticación**: Token de usuario (paciente) requerido
- **Parámetros de Entrada**:
  ```json
  {
    "appointment_date": "08-05-2025",
    "appointment_time": "14:30"
  }
  ```
- **Restricciones**:
  - Solo los pacientes pueden cancelar citas
  - No se pueden cancelar citas pasadas
  - Solo se pueden cancelar citas en estado "pendiente"
- **Respuesta Exitosa**:
  ```json
  {
    "message": "Cita cancelada con éxito"
  }
  ```

### Gestión de Pagos

#### Crear Pago
- **URL**: `/payments?token=token_usuario`
- **Método**: `POST`
- **Descripción**: Procesa un pago para una cita médica utilizando Mercado Pago
- **Autenticación**: Token de usuario requerido
- **Parámetros de Entrada**:
  ```json
  {
    "amount": 50.00,
    "token": "token_mercadopago",
    "payment_method_id": "visa"
  }
  ```
- **Respuesta Exitosa**:
  ```json
  {
    "success": true,
    "payment_id": "12345678",
    "status": "approved"
  }
  ```

## Autenticación y Seguridad

La autenticación se realiza utilizando un token generado al hacer login. Este token debe ser incluido como parámetro de consulta (`?token=token_usuario`) para poder acceder a los endpoints que requieren autenticación.

### Middleware de Autenticación

El middleware de autenticación verifica la validez del token y recupera la información del usuario correspondiente. Si el token es válido, la solicitud se procesa; de lo contrario, se devuelve un mensaje de error.

## Roles de Usuario

- **Paciente**: Puede registrar citas, consultar sus citas y cancelarlas.
- **Doctor/Médico**: Puede ver las citas programadas para él, confirmar o rechazar citas.

## Notas Adicionales

- Todas las respuestas de la API están en formato JSON
- Los errores se devuelven con mensajes descriptivos
- Las fechas se manejan en formato d-m-Y para entrada y salida de datos
- Las contraseñas se almacenan con hash utilizando la función `password_hash()` de PHP
- La API implementa validaciones para asegurar la integridad de los datos