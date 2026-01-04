<?php

/* Escape HTML */
if (!function_exists('h')) {
    function h($s){
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

/* Redirect helper */
if (!function_exists('redirect')) {
    function redirect(string $path){
        header("Location: " . BASE_URL . $path);
        exit;
    }
}

/* Check root */
if (!function_exists('is_root')) {
    function is_root(): bool {
        return (($_SESSION['privilegi'] ?? '') === 'root');
    }
}

/* Require root (BLOCCA ACCESSO) */
if (!function_exists('require_root')) {
    function require_root(){
        if (empty($_SESSION['utente_id']) || !is_root()) {
            redirect('/dashboard.php');
        }
    }
}

/* -------------------------------------------------
   DB helpers
------------------------------------------------- */
if (!function_exists('db')) {
    function db(): mysqli {
        global $mysqli;
        return $mysqli;
    }
}

/* Guests */
if (!function_exists('create_guest')) {
    function create_guest(string $name, ?string $contacts = null): ?int {
        $stmt = db()->prepare("INSERT INTO guests (full_name, contact_info) VALUES (?, ?)");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("ss", $name, $contacts);
        $ok = $stmt->execute();
        $id = $ok ? (int)$stmt->insert_id : null;
        $stmt->close();

        return $ok ? $id : null;
    }
}

if (!function_exists('get_guest')) {
    function get_guest(int $id): ?array {
        $stmt = db()->prepare("SELECT * FROM guests WHERE id=? LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('list_guests')) {
    function list_guests(?string $search = null, int $limit = 100): array {
        $limit = max(1, $limit);
        $conn = db();

        if ($search !== null && $search !== '') {
            $like = '%' . $search . '%';
            $stmt = $conn->prepare("SELECT * FROM guests WHERE full_name LIKE ? OR contact_info LIKE ? ORDER BY full_name ASC LIMIT ?");
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param("ssi", $like, $like, $limit);
        } else {
            $stmt = $conn->prepare("SELECT * FROM guests ORDER BY created_at DESC LIMIT ?");
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param("i", $limit);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('update_guest')) {
    function update_guest(int $id, string $name, ?string $contacts = null): bool {
        $stmt = db()->prepare("UPDATE guests SET full_name=?, contact_info=? WHERE id=?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ssi", $name, $contacts, $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('delete_guest')) {
    function delete_guest(int $id): bool {
        $stmt = db()->prepare("DELETE FROM guests WHERE id=?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

/* Guest documents */
if (!function_exists('add_guest_document')) {
    function add_guest_document(int $guestId, string $type, string $number, ?string $validUntil = null): ?int {
        $stmt = db()->prepare("INSERT INTO guest_documents (guest_id, document_type, document_number, valid_until) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("isss", $guestId, $type, $number, $validUntil);
        $ok = $stmt->execute();
        $id = $ok ? (int)$stmt->insert_id : null;
        $stmt->close();

        return $ok ? $id : null;
    }
}

if (!function_exists('list_guest_documents')) {
    function list_guest_documents(int $guestId): array {
        $stmt = db()->prepare("SELECT * FROM guest_documents WHERE guest_id=? ORDER BY valid_until DESC, id DESC");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("i", $guestId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('update_guest_document')) {
    function update_guest_document(int $id, string $type, string $number, ?string $validUntil = null): bool {
        $stmt = db()->prepare("UPDATE guest_documents SET document_type=?, document_number=?, valid_until=? WHERE id=?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("sssi", $type, $number, $validUntil, $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('delete_guest_document')) {
    function delete_guest_document(int $id): bool {
        $stmt = db()->prepare("DELETE FROM guest_documents WHERE id=?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

/* Room types */
if (!function_exists('create_room_type')) {
    function create_room_type(string $name, ?string $description = null): ?int {
        $stmt = db()->prepare("INSERT INTO room_types (name, description) VALUES (?, ?)");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("ss", $name, $description);
        $ok = $stmt->execute();
        $id = $ok ? (int)$stmt->insert_id : null;
        $stmt->close();

        return $ok ? $id : null;
    }
}

if (!function_exists('get_room_type')) {
    function get_room_type(int $id): ?array {
        $stmt = db()->prepare("SELECT * FROM room_types WHERE id=? LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('list_room_types')) {
    function list_room_types(): array {
        $stmt = db()->prepare("SELECT * FROM room_types ORDER BY name ASC");
        if (!$stmt) {
            return [];
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('update_room_type')) {
    function update_room_type(int $id, string $name, ?string $description = null): bool {
        $stmt = db()->prepare("UPDATE room_types SET name=?, description=? WHERE id=?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ssi", $name, $description, $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('delete_room_type')) {
    function delete_room_type(int $id): bool {
        $stmt = db()->prepare("DELETE FROM room_types WHERE id=?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

/* Rooms */
if (!function_exists('create_room')) {
    function create_room(int $cameraId, ?int $roomTypeId = null, ?string $label = null, string $status = 'available'): ?int {
        $stmt = db()->prepare("INSERT INTO rooms (camera_id, room_type_id, label, status) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("iiss", $cameraId, $roomTypeId, $label, $status);
        $ok = $stmt->execute();
        $id = $ok ? (int)$stmt->insert_id : null;
        $stmt->close();

        return $ok ? $id : null;
    }
}

if (!function_exists('get_room')) {
    function get_room(int $id): ?array {
        $stmt = db()->prepare("SELECT * FROM rooms WHERE id=? LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('get_room_by_camera')) {
    function get_room_by_camera(int $cameraId): ?array {
        $stmt = db()->prepare("SELECT * FROM rooms WHERE camera_id=? LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("i", $cameraId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('list_rooms')) {
    function list_rooms(int $limit = 100): array {
        $limit = max(1, $limit);
        $stmt = db()->prepare("SELECT * FROM rooms ORDER BY id DESC LIMIT ?");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('update_room')) {
    function update_room(int $id, int $cameraId, ?int $roomTypeId = null, ?string $label = null, string $status = 'available'): bool {
        $stmt = db()->prepare("UPDATE rooms SET camera_id=?, room_type_id=?, label=?, status=? WHERE id=?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("iissi", $cameraId, $roomTypeId, $label, $status, $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('delete_room')) {
    function delete_room(int $id): bool {
        $stmt = db()->prepare("DELETE FROM rooms WHERE id=?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

/* Bookings */
if (!function_exists('create_booking')) {
    function create_booking(int $guestId, int $roomId, string $checkIn, string $checkOut, string $status = 'pending'): ?int {
        $stmt = db()->prepare("INSERT INTO bookings (guest_id, room_id, check_in, check_out, status) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("iisss", $guestId, $roomId, $checkIn, $checkOut, $status);
        $ok = $stmt->execute();
        $id = $ok ? (int)$stmt->insert_id : null;
        $stmt->close();

        return $ok ? $id : null;
    }
}

if (!function_exists('get_booking')) {
    function get_booking(int $id): ?array {
        $stmt = db()->prepare("SELECT * FROM bookings WHERE id=? LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('list_bookings_by_guest')) {
    function list_bookings_by_guest(int $guestId): array {
        $stmt = db()->prepare("SELECT * FROM bookings WHERE guest_id=? ORDER BY check_in DESC");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("i", $guestId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('update_booking_status')) {
    function update_booking_status(int $id, string $status): bool {
        $stmt = db()->prepare("UPDATE bookings SET status=? WHERE id=?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("si", $status, $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('update_booking_dates')) {
    function update_booking_dates(int $id, string $checkIn, string $checkOut): bool {
        $stmt = db()->prepare("UPDATE bookings SET check_in=?, check_out=? WHERE id=?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ssi", $checkIn, $checkOut, $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

/* Payments */
if (!function_exists('record_payment')) {
    function record_payment(int $bookingId, string $method, float $amount, string $status = 'pending', ?string $paidAt = null): ?int {
        $stmt = db()->prepare("INSERT INTO payments (booking_id, method, amount, status, paid_at) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("isdss", $bookingId, $method, $amount, $status, $paidAt);
        $ok = $stmt->execute();
        $id = $ok ? (int)$stmt->insert_id : null;
        $stmt->close();

        return $ok ? $id : null;
    }
}

if (!function_exists('list_payments_for_booking')) {
    function list_payments_for_booking(int $bookingId): array {
        $stmt = db()->prepare("SELECT * FROM payments WHERE booking_id=? ORDER BY created_at DESC");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('update_payment_status')) {
    function update_payment_status(int $id, string $status, ?string $paidAt = null): bool {
        $stmt = db()->prepare("UPDATE payments SET status=?, paid_at=? WHERE id=?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ssi", $status, $paidAt, $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

/* Housekeeping */
if (!function_exists('schedule_housekeeping_task')) {
    function schedule_housekeeping_task(int $roomId, string $taskType, ?string $scheduledAt = null, string $status = 'pending', ?string $notes = null): ?int {
        $stmt = db()->prepare("INSERT INTO housekeeping_tasks (room_id, task_type, scheduled_at, status, notes) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("issss", $roomId, $taskType, $scheduledAt, $status, $notes);
        $ok = $stmt->execute();
        $id = $ok ? (int)$stmt->insert_id : null;
        $stmt->close();

        return $ok ? $id : null;
    }
}

if (!function_exists('list_housekeeping_tasks_for_room')) {
    function list_housekeeping_tasks_for_room(int $roomId): array {
        $stmt = db()->prepare("SELECT * FROM housekeeping_tasks WHERE room_id=? ORDER BY scheduled_at ASC, id DESC");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("i", $roomId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('update_housekeeping_status')) {
    function update_housekeeping_status(int $id, string $status): bool {
        $stmt = db()->prepare("UPDATE housekeeping_tasks SET status=? WHERE id=?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("si", $status, $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('mark_housekeeping_performed')) {
    function mark_housekeeping_performed(int $id, ?string $performedAt = null, string $status = 'done'): bool {
        $stmt = db()->prepare("UPDATE housekeeping_tasks SET performed_at=?, status=? WHERE id=?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ssi", $performedAt, $status, $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}
