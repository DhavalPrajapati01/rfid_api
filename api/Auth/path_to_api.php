<?php
header("Content-Type: application/json");
require_once "/home/u980461598/domains/intervein.dprofiz.com/public_html/Rfid_api/Database/db_connection.php"; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Required fields
$required_fields = ['user_id', 'organization_name', 'address', 'city', 'area', 'contact_person_name', 'contact_person_mobile', 'email', 'phone_number', 'num_dustbins', 'num_floors'];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing or empty field: $field"]);
        exit;
    }
}

// Assign and trim input values
$user_id = trim($data['user_id']);

// ✅ Validate user_id strength
$pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/'; // At least one lowercase, uppercase, number, special char
if (!preg_match($pattern, $user_id)) {
    http_response_code(400);
    echo json_encode(["error" => "User ID must contain at least one uppercase letter, one lowercase letter, one number, and one special character"]);
    exit;
}

$organization_name = strtoupper(trim($data['organization_name']));
$address = trim($data['address']);
$city = strtoupper(trim($data['city']));
$area = strtoupper(trim($data['area']));
$contact_person_name = trim($data['contact_person_name']);
$contact_person_mobile = trim($data['contact_person_mobile']);
$email = trim($data['email']);
$phone_number = trim($data['phone_number']);
$num_dustbins = intval($data['num_dustbins']);
$num_floors = intval($data['num_floors']);

// Check for duplicates
$check_query = $conn->prepare("SELECT * FROM organizations WHERE user_id = ? OR email = ? OR phone_number = ?");
$check_query->bind_param("sss", $user_id, $email, $phone_number);
$check_query->execute();
$check_result = $check_query->get_result();

if ($check_result->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["error" => "Duplicate entry: user_id, email, or phone number already exists"]);
    $check_query->close();
    $conn->close();
    exit;
}
$check_query->close();

// Generate organization_id
$org_short = substr(preg_replace('/[^A-Z]/', '', $organization_name), 0, 5);
$city_short = substr($city, 0, 3);
$area_short = substr($area, 0, 3);
$random_num = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
$organization_id = "{$org_short}{$city_short}{$area_short}{$random_num}";

// Insert organization
$stmt = $conn->prepare("INSERT INTO organizations (user_id, organization_id, organization_name, address, city, area, contact_person_name, contact_person_mobile, email, phone_number, num_dustbins, num_floors) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssssssis", $user_id, $organization_id, $organization_name, $address, $city, $area, $contact_person_name, $contact_person_mobile, $email, $phone_number, $num_dustbins, $num_floors);

if ($stmt->execute()) {
    echo json_encode(["success" => "Organization registered successfully", "organization_id" => $organization_id]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to register organization"]);
}

$stmt->close();
$conn->close();
?>
