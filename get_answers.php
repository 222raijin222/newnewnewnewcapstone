<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    exit("Invalid request.");
}

$resident_id = intval($_GET['id']);

// Fetch census submission by resident_id
$query = $conn->prepare("
    SELECT cs.*, 
           r.first_name as resident_first_name,
           r.last_name as resident_last_name,
           r.birthdate as resident_birthdate,
           r.gender as resident_gender,
           r.contact_number as resident_contact,
           r.address as resident_address,
           r.barangay_id,
           br.barangay_name,
           p.purok_name
    FROM census_submissions cs
    LEFT JOIN residents r ON cs.id = r.id
    LEFT JOIN barangay_registration br ON r.barangay_id = br.id
    LEFT JOIN puroks p ON r.purok_id = p.purok_id
    WHERE cs.id = ?
");
$query->bind_param("i", $resident_id);
$query->execute();
$result = $query->get_result();

if ($row = $result->fetch_assoc()) {
    // Fetch household members for this census submission
    $memberQuery = $conn->prepare("
        SELECT * FROM household_members 
        WHERE household_id = ? 
        ORDER BY relationship, age DESC
    ");
    $memberQuery->bind_param("i", $resident_id);
    $memberQuery->execute();
    $memberResult = $memberQuery->get_result();
    
    $household_members = [];
    while ($member = $memberResult->fetch_assoc()) {
        $household_members[] = $member;
    }
    $memberQuery->close();
    
    // Return data as JSON for use in dashboard, analytics, and resident profile
    header('Content-Type: application/json');
    
    $response = [
        'success' => true,
        'data' => [
            'personal_info' => [
                'first_name' => $row['first_name'] ?? $row['resident_first_name'] ?? '',
                'last_name' => $row['last_name'] ?? $row['resident_last_name'] ?? '',
                'age' => $row['age'] ?? calculateAge($row['resident_birthdate'] ?? '') ?? '',
                'gender' => $row['gender'] ?? $row['resident_gender'] ?? '',
                'contact_no' => $row['contact_no'] ?? $row['resident_contact'] ?? '',
                'birth_day' => $row['birth_day'] ?? '',
                'birth_month' => $row['birth_month'] ?? '',
                'birth_year' => $row['birth_year'] ?? '',
                'birthdate' => $row['resident_birthdate'] ?? '',
                'barangay_name' => $row['barangay_name'] ?? '',
                'purok_name' => $row['purok_name'] ?? ''
            ],
            'address_info' => [
                'province' => $row['province'] ?? '',
                'city' => $row['city'] ?? '',
                'barangay' => $row['barangay'] ?? $row['barangay_name'] ?? '',
                'building' => $row['building'] ?? '',
                'house_lot' => $row['house_lot'] ?? '',
                'street' => $row['street'] ?? '',
                'full_address' => $row['resident_address'] ?? ''
            ],
            'health_info' => [
                'female_death' => $row['female_death'] ?? 'No',
                'female_death_age' => $row['female_death_age'] ?? '',
                'female_death_cause' => $row['female_death_cause'] ?? '',
                'child_death' => $row['child_death'] ?? 'No',
                'child_death_age' => $row['child_death_age'] ?? '',
                'child_death_sex' => $row['child_death_sex'] ?? '',
                'child_death_cause' => $row['child_death_cause'] ?? '',
                'disease_1' => $row['disease_1'] ?? '',
                'disease_2' => $row['disease_2'] ?? '',
                'disease_3' => $row['disease_3'] ?? '',
                'need_1' => $row['need_1'] ?? '',
                'need_2' => $row['need_2'] ?? '',
                'need_3' => $row['need_3'] ?? ''
            ],
            'facilities_info' => [
                'water_supply' => $row['water_supply'] ?? '',
                'toilet_facility' => $row['toilet_facility'] ?? '',
                'toilet_other' => $row['toilet_other'] ?? '',
                'garbage_disposal' => $row['garbage_disposal'] ?? '',
                'segregate' => $row['segregate'] ?? '',
                'lighting_fuel' => $row['lighting_fuel'] ?? '',
                'lighting_other' => $row['lighting_other'] ?? '',
                'cooking_fuel' => $row['cooking_fuel'] ?? '',
                'cooking_other' => $row['cooking_other'] ?? ''
            ],
            'economic_info' => [
                'source_income' => $row['source_income'] ?? '',
                'status_work_business' => $row['status_work_business'] ?? '',
                'place_work_business' => $row['place_work_business'] ?? '',
                'submitted_at' => $row['submitted_at'] ?? ''
            ],
            'household_info' => [
                'total_members' => count($household_members),
                'members' => $household_members
            ],
            'demographic_summary' => [
                'household_size' => count($household_members) + 1, // +1 for the head
                'age_distribution' => calculateHouseholdAgeDistribution($household_members),
                'gender_distribution' => calculateHouseholdGenderDistribution($household_members),
                'employment_summary' => calculateHouseholdEmploymentSummary($household_members)
            ]
        ]
    ];
    
    echo json_encode($response);
    
} else {
    // Return error as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'No census data found for this resident.'
    ]);
}

$query->close();

// Helper functions for demographic calculations
function calculateHouseholdAgeDistribution($members) {
    $distribution = [
        '0-17' => 0,
        '18-24' => 0,
        '25-34' => 0,
        '35-44' => 0,
        '45-59' => 0,
        '60+' => 0
    ];
    
    foreach ($members as $member) {
        $age = $member['age'] ?? 0;
        if ($age < 18) {
            $distribution['0-17']++;
        } elseif ($age >= 18 && $age <= 24) {
            $distribution['18-24']++;
        } elseif ($age >= 25 && $age <= 34) {
            $distribution['25-34']++;
        } elseif ($age >= 35 && $age <= 44) {
            $distribution['35-44']++;
        } elseif ($age >= 45 && $age <= 59) {
            $distribution['45-59']++;
        } else {
            $distribution['60+']++;
        }
    }
    
    return $distribution;
}

function calculateHouseholdGenderDistribution($members) {
    $distribution = [
        'Male' => 0,
        'Female' => 0,
        'Other' => 0
    ];
    
    foreach ($members as $member) {
        $gender = $member['sex'] ?? 'Other';
        if (isset($distribution[$gender])) {
            $distribution[$gender]++;
        } else {
            $distribution['Other']++;
        }
    }
    
    return $distribution;
}

function calculateHouseholdEmploymentSummary($members) {
    $summary = [];
    
    foreach ($members as $member) {
        $status = $member['employment_status'] ?? 'Unknown';
        if (!isset($summary[$status])) {
            $summary[$status] = 0;
        }
        $summary[$status]++;
    }
    
    return $summary;
}

$conn->close();
?>