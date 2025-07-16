<?php
// Database connection settings
include_once 'db_connection.php';

// Fetch search parameters from the query string
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$location = isset($_GET['location']) ? $_GET['location'] : null;
$status = isset($_GET['status']) ? $_GET['status'] : null;

// Build the SQL query
$sql = "SELECT id, location, capacity, status, timestamp FROM Dustbins WHERE 1=1";
$pa
