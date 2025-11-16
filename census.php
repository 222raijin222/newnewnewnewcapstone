<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Connect to database
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->beginTransaction();

        // 🧾 Save household representative info
        $stmt = $conn->prepare("
            INSERT INTO census_submissions (
                first_name, last_name, age, gender, contact_no,
                birth_day, birth_month, birth_year,
                province, city, barangay, building, house_lot, street,
                female_death, female_death_age, female_death_cause,
                child_death, child_death_age, child_death_sex, child_death_cause,
                disease_1, disease_2, disease_3,
                need_1, need_2, need_3,
                water_supply, toilet_facility, toilet_other,
                garbage_disposal, segregate, lighting_fuel, lighting_other,
                cooking_fuel, cooking_other, source_income, status_work_business, place_work_business,
                submitted_at
            ) VALUES (
                :first_name, :last_name, :age, :gender, :contact_no,
                :birth_day, :birth_month, :birth_year,
                :province, :city, :barangay, :building, :house_lot, :street,
                :female_death, :female_death_age, :female_death_cause,
                :child_death, :child_death_age, :child_death_sex, :child_death_cause,
                :disease_1, :disease_2, :disease_3,
                :need_1, :need_2, :need_3,
                :water_supply, :toilet_facility, :toilet_other,
                :garbage_disposal, :segregate, :lighting_fuel, :lighting_other,
                :cooking_fuel, :cooking_other, :source_income, :status_work_business, :place_work_business,
                NOW()
            )
        ");

        $stmt->execute([
            ':first_name' => $_POST['first_name'] ?? '',
            ':last_name' => $_POST['last_name'] ?? '',
            ':age' => $_POST['age'] ?? '',
            ':gender' => $_POST['gender'] ?? '',
            ':contact_no' => $_POST['contact_no'] ?? '',
            ':birth_day' => $_POST['birth_day'] ?? '',
            ':birth_month' => $_POST['birth_month'] ?? '',
            ':birth_year' => $_POST['birth_year'] ?? '',
            ':province' => $_POST['province'] ?? '',
            ':city' => $_POST['city'] ?? '',
            ':barangay' => $_POST['barangay'] ?? '',
            ':building' => $_POST['building'] ?? '',
            ':house_lot' => $_POST['house_lot'] ?? '',
            ':street' => $_POST['street'] ?? '',
            ':female_death' => $_POST['female_death'] ?? '',
            ':female_death_age' => $_POST['female_death_age'] ?? null,
            ':female_death_cause' => $_POST['female_death_cause'] ?? null,
            ':child_death' => $_POST['child_death'] ?? '',
            ':child_death_age' => $_POST['child_death_age'] ?? null,
            ':child_death_sex' => $_POST['child_death_sex'] ?? null,
            ':child_death_cause' => $_POST['child_death_cause'] ?? null,
            ':disease_1' => $_POST['disease_1'] ?? '',
            ':disease_2' => $_POST['disease_2'] ?? '',
            ':disease_3' => $_POST['disease_3'] ?? '',
            ':need_1' => $_POST['need_1'] ?? '',
            ':need_2' => $_POST['need_2'] ?? '',
            ':need_3' => $_POST['need_3'] ?? '',
            ':water_supply' => $_POST['water_supply'] ?? '',
            ':toilet_facility' => $_POST['toilet_facility'] ?? '',
            ':toilet_other' => $_POST['toilet_other'] ?? '',
            ':garbage_disposal' => $_POST['garbage_disposal'] ?? '',
            ':segregate' => $_POST['segregate'] ?? '',
            ':lighting_fuel' => $_POST['lighting_fuel'] ?? '',
            ':lighting_other' => $_POST['lighting_other'] ?? '',
            ':cooking_fuel' => $_POST['cooking_fuel'] ?? '',
            ':cooking_other' => $_POST['cooking_other'] ?? '',
            ':source_income' => $_POST['income_source'] ?? null,
            ':status_work_business' => $_POST['work_status'] ?? null,
            ':place_work_business' => $_POST['work_place'] ?? null
        ]);

        $household_id = $conn->lastInsertId();

       // 👨‍👩‍👧‍👦 Save household members
$member_stmt = $conn->prepare("
    INSERT INTO household_members (
        household_id, member_name, age, birth_month, birth_year, sex,
        philhealth_have, philhealth_id, pwd_have, pwd_id, relationship,
        civil_status, religion, citizenship, education_level, currently_enrolled,
        school_level, school_place, employment_status, work_details, monthly_income
    ) VALUES (
        :household_id, :member_name, :age, :birth_month, :birth_year, :sex,
        :philhealth_have, :philhealth_id, :pwd_have, :pwd_id, :relationship,
        :civil_status, :religion, :citizenship, :education_level, :currently_enrolled,
        :school_level, :school_place, :employment_status, :work_details, :monthly_income
    )
");

$i = 1;
while (!empty($_POST["member_name_$i"])) {
    $member_stmt->execute([
        ':household_id' => $household_id,
        ':member_name' => $_POST["member_name_$i"] ?? '',
        ':age' => $_POST["age_$i"] ?? '',
        ':birth_month' => $_POST["birth_month_$i"] ?? '',
        ':birth_year' => $_POST["birth_year_$i"] ?? '',
        ':sex' => $_POST["sex_$i"] ?? '',
        ':philhealth_have' => $_POST["philhealth_have_$i"] ?? '',
        ':philhealth_id' => $_POST["philhealth_id_$i"] ?? '',
        ':pwd_have' => $_POST["pwd_have_$i"] ?? '',
        ':pwd_id' => $_POST["pwd_id_$i"] ?? '',
        ':relationship' => $_POST["relationship_$i"] ?? '',
        ':civil_status' => $_POST["civil_status_$i"] ?? '',
        ':religion' => $_POST["religion_$i"] ?? '',
        ':citizenship' => $_POST["citizenship_$i"] ?? '',
        ':education_level' => $_POST["education_level_$i"] ?? '',
        ':currently_enrolled' => $_POST["currently_enrolled_$i"] ?? '',
        ':school_level' => $_POST["school_level_$i"] ?? '',
        ':school_place' => $_POST["school_place_$i"] ?? '',
        ':employment_status' => $_POST["employment_status_$i"] ?? '',
        ':work_details' => $_POST["work_details_$i"] ?? '',
        ':monthly_income' =>$_POST["monthly_income_$i"] ?? ''
    ]);
    $i++;
}

$conn->commit();
echo "<script>alert('✅ Census form submitted successfully!'); window.location.href='census.php';</script>";


    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo "❌ Database error: " . $e->getMessage();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Census Form - Barangay System</title>
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
    body {
        font-family: "Poppins", sans-serif;
        background: #f4f6fa;
        margin: 0;
        padding: 0;
    }
    .container {
        max-width: 750px;
        background: #fff;
        margin: 40px auto;
        padding: 30px 40px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    h2, h1 {
        text-align: center;
        color: #fefeffff;
        margin-bottom: 25px;
    }
    .form-group {
        margin-bottom: 18px;
    }
    label {
        display: block;
        font-weight: 600;
        margin-bottom: 6px;
        color: #333;
    }
    input[type="text"],
    input[type="number"],
    select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 14px;
    }
    input[readonly] {
        background-color: #f1f1f1;
        color: #555;
    }
    .birthdate-group {
        display: flex;
        gap: 10px;
    }
    .birthdate-group select {
        flex: 1;
    }
    .form-row {
        display: flex;
        gap: 15px;
    }
    .form-row .form-group {
        flex: 1;
    }
    button {
        display: block;
        width: 100%;
        background: #007bff;
        color: #fff;
        border: none;
        padding: 12px;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
        transition: 0.3s;
    }
    button:hover {
        background: #0056b3;
    }
</style>
</head>
<body>

<!-- 🔙 Back Button -->
<div style="margin: 0px;">
    <button type="button" 
            onclick="window.location.href='resident.php';"  
            style="background-color:#1d3b71; 
            color:white; 
            border:none; 
            padding:10px 20px; 
            border-radius:4px; 
            cursor:pointer;">
        ← Go to main page
    </button>
</div> 

<div class="container">
    <form class="census-form" method="POST" action="">
        <div class="census-container">
            <div class="census-header">
                <h1>Barangay Census Form</h1>
                <p>Please fill out the form below completely</p>
            </div>

            <!-- Basic Info -->
            <div class="form-row">
                <label for="household_head">Household Representative Name</label>
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" pattern="[A-Za-z ]+" title="Letters only" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" pattern="[A-Za-z ]+" title="Letters only" required>
                </div>
            </div>

            <!-- Birthdate -->
<div class="form-group">
    <label>Birthdate</label>
    <div class="birthdate-group">
        <select id="birth_day" name="birth_day" required>
            <option value="">Day</option>
            <?php for ($d = 1; $d <= 31; $d++): ?>
                <option value="<?= $d ?>"><?= $d ?></option>
            <?php endfor; ?>
        </select>

        <select id="birth_month" name="birth_month" required>
            <option value="">Month</option>
            <?php 
                $months = [
                    1 => 'January', 
                    2 => 'February', 
                    3 => 'March', 
                    4 => 'April', 
                    5 => 'May', 
                    6 => 'June', 
                    7 => 'July', 
                    8 => 'August', 
                    9 => 'September', 
                    10 => 'October', 
                    11 => 'November', 
                    12 => 'December'
                ];
                foreach ($months as $num => $name): 
            ?>
                <option value="<?= $name ?>"><?= $name ?></option>
            <?php endforeach; ?>
        </select>

        <select id="birth_year" name="birth_year" required>
            <option value="">Year</option>
            <?php 
                $currentYear = date('Y');
                for ($y = $currentYear; $y >= 1900; $y--): 
            ?>
                <option value="<?= $y ?>"><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>
</div>

<!-- Age (auto-calculated) -->
<div class="form-row">
    <div class="form-group">
        <label for="age">Age</label>
        <input type="number" id="age" name="age" min="1" max="120" required readonly style="background:#f0f0f0; cursor:not-allowed;">
    </div>

    <script>
// 🧮 Automatically compute age based on birthdate inputs
document.addEventListener('DOMContentLoaded', () => {
    const daySelect = document.getElementById('birth_day');
    const monthSelect = document.getElementById('birth_month');
    const yearSelect = document.getElementById('birth_year');
    const ageInput = document.getElementById('age');

    function calculateAge() {
        const birthDay = parseInt(daySelect.value);
        const birthMonthName = monthSelect.value;
        const birthYear = parseInt(yearSelect.value);

        if (!birthDay || !birthMonthName || !birthYear) {
            ageInput.value = '';
            return;
        }

        // 🗓️ Convert month name to number (1–12)
        const monthMap = {
            January: 1, February: 2, March: 3, April: 4,
            May: 5, June: 6, July: 7, August: 8,
            September: 9, October: 10, November: 11, December: 12
        };
        const birthMonth = monthMap[birthMonthName];

        const today = new Date();
        let age = today.getFullYear() - birthYear;

        const currentMonth = today.getMonth() + 1;
        const currentDay = today.getDate();

        // 👇 Adjust if birthday hasn't occurred yet this year
        if (currentMonth < birthMonth || (currentMonth === birthMonth && currentDay < birthDay)) {
            age--;
        }

        // 🧾 Set computed age
        ageInput.value = age >= 0 ? age : 0;
    }

    // 👂 Listen for changes
    daySelect.addEventListener('change', calculateAge);
    monthSelect.addEventListener('change', calculateAge);
    yearSelect.addEventListener('change', calculateAge);
});
</script>

                <div class="form-group">
                    <label for="gender">Sex</label>
                    <select id="gender" name="gender" required>
                        <option value="">Select Sex</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="contact_no">Contact No.:</label>
                <input type="text" id="contact_no" name="contact_no" maxlength="10" 
                       pattern="\d{10}" placeholder="(+63) ph number" 
                       oninput="this.value = this.value.replace(/[^0-9]/g, '');" required>
            </div>


            <!-- Location Info -->
            <div class="form-row">
                <div class="form-group">
                    <label for="province">Province</label>
                    <input type="text" id="province" name="province" value="Pampanga" readonly>
                </div>
                <div class="form-group">
                    <label for="city">City/Municipality</label>
                    <input type="text" id="city" name="city" value="San Fernando" readonly>
                </div>
            </div>

            <div class="form-group">
                <label for="barangay">Barangay</label>
                <select id="barangay" name="barangay" required>
                    <option value="">Select Barangay</option>
                    <option value="Alasas">Brgy. Alasas</option>
                    <option value="Del Rosario">Brgy. Del Rosario</option>
                    <option value="Saguin">Brgy. Saguin</option>
                    <option value="Baliti">Brgy. Baliti</option>
                    <option value="Bulaon">Brgy. Bulaon</option>
                    <option value="Calulut">Brgy. Calulut</option>
                    <option value="Dela Paz Norte">Brgy. Dela Paz Norte</option>
                    <option value="Dela Paz Sur">Brgy. Dela Paz Sur</option>
                    <option value="Del Carmen">Brgy. Del Carmen</option>
                    <option value="Del Pilar">Brgy. Del Pilar</option>
                    <option value="Dolores">Brgy. Dolores</option>
                    <option value="Juliana">Brgy. Juliana</option>
                    <option value="Lara">Brgy. Lara</option>
                    <option value="Lourdes">Brgy. Lourdes</option>
                    <option value="Magliman">Brgy. Magliman</option>
                    <option value="Maimpis">Brgy. Maimpis</option>
                    <option value="Malino">Brgy. Malino</option>
                    <option value="Malpitic">Brgy. Malpitic</option>
                    <option value="Pandaras">Brgy. Pandaras</option>
                    <option value="Panipuan">Brgy. Panipuan</option>
                    <option value="Pulung Bulo">Brgy. Pulung Bulo</option>
                    <option value="Quebiawan">Brgy. Quebiawan</option>
                    <option value="San Agustin">Brgy. San Agustin</option>
                    <option value="San Felipe">Brgy. San Felipe</option>
                    <option value="San Isidro">Brgy. San Isidro</option>
                    <option value="San Jose">Brgy. San Jose</option>
                    <option value="San Juan">Brgy. San Juan</option>
                    <option value="San Nicolas">Brgy. San Nicolas</option>
                    <option value="San Pedro Cutud">Brgy. San Pedro Cutud</option>
                    <option value="Santa Lucia">Brgy. Santa Lucia</option>
                    <option value="Santa Teresita">Brgy. Santa Teresita</option>
                    <option value="Santo Niño">Brgy. Santo Niño</option>
                    <option value="Santo Rosario">Brgy. Santo Rosario</option>
                    <option value="Sindalan">Brgy. Sindalan</option>
                    <option value="Telabastagan">Brgy. Telabastagan</option>
                </select>
            </div>

            <div class="form-group">
                <label for="building">Room/Floor/Unit No. & Building Name</label>
                <input type="text" id="building" name="building">
            </div>

            <div class="form-group">
                <label for="house_lot">House/Lot & Block No.</label>
                <input type="text" id="house_lot" name="house_lot" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
            </div>

        <div class="form-group">
            <label for="street">Street Name</label>
            <select id="street" name="street" required>
                <option value="">Select Street</option>
            
                <!-- 📝 Add street names dynamically here later -->
            </select>
        </div>


<!-- ========================= -->
<!-- 👨‍👩‍👧 Household Members -->
<!-- ========================= -->
<h3>Household Members Information</h3>

<!-- 🔘 Button to open popup -->
<button type="button" id="openHouseholdBtn" class="open-btn">
  👨‍👩‍👧 View Household Members Table
</button>

<!-- 🪟 Modal Container -->
<div id="householdModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>

    <div class="table-container">
      <button type="button" id="addMemberBtn" class="add-btn">➕ Add Member</button>

      <div class="scroll-container">
        <table class="household-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Age</th>
              <th>Birth Month</th>
              <th>Birth Year</th>
              <th>Sex</th>
              <th>PhilHealth?</th>
              <th>PhilHealth ID</th>
              <th>PWD?</th>
              <th>PWD ID</th>
              <th>Relationship</th>
              <th>Civil Status</th>
              <th>Religion</th>
              <th>Citizenship</th>
              <th>Highest Level of Education Completed?</th>
              <th>Currently Enrolled</th>
              <th>School Level</th>
              <th>School Name</th>
              <th>Employment Status</th>
              <th>Field of Work</th>
              <th>Monthly Income (₱)</th>
              <th>❌</th>
            </tr>
          </thead>
          <tbody id="householdBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<style>
/* 🔘 Button styling */
.open-btn {
  background: #004080;
  color: #fff;
  padding: 8px 14px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: 0.3s;
}
.open-btn:hover {
  background: #0059b3;
}

/* 🪟 Modal styling */
.modal {
  display: none;
  position: fixed;
  z-index: 999;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0,0,0,0.6);
  overflow-y: auto;
}
.modal-content {
  background-color: #fff;
  margin: 3% auto;
  padding: 20px;
  border-radius: 10px;
  width: 95%;
  max-width: 1700px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 4px 10px rgba(0,0,0,0.3);
}
.close {
  color: #ff0000;
  float: right;
  font-size: 28px;
  font-weight: bold;
  cursor: pointer;
}
.close:hover {
  color: #cc0000;
}

/* 📋 Table styling */
.table-container {
  margin-top: 10px;
  border: 1px solid #ccc;
  padding: 10px;
  border-radius: 8px;
  background: #f8f9fa;
}
.scroll-container {
  overflow-x: auto;
  max-height: 70vh;
}
.add-btn {
  background: #004080;
  color: #fff;
  padding: 6px 12px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  margin-bottom: 8px;
}
.household-table {
  width: 2400px; /* expanded width for extra column */
  border-collapse: collapse;
  font-size: 13px;
}
.household-table th,
.household-table td {
  border: 1px solid #ccc;
  padding: 6px;
  text-align: center;
  vertical-align: middle;
}
.household-table input,
.household-table select {
  width: 100%;
  padding: 3px;
  font-size: 12px;
  box-sizing: border-box;
}
.household-table input[name^="member_name_"] {
  width: 240px;
}
</style>

<script>
let memberCount = 0;

// 🪟 Modal logic
const modal = document.getElementById("householdModal");
const openBtn = document.getElementById("openHouseholdBtn");
const closeBtn = document.getElementsByClassName("close")[0];

openBtn.onclick = () => {
  modal.style.display = "block";
  document.body.style.overflow = "hidden";
};
closeBtn.onclick = () => {
  modal.style.display = "none";
  document.body.style.overflow = "auto";
};
window.onclick = (event) => {
  if (event.target === modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
  }
};

// 🧩 Add new household member
document.getElementById("addMemberBtn").addEventListener("click", () => {
  memberCount++;
  const row = document.createElement("tr");

  // ✅ Proper date handling
  const currentYear = new Date().getFullYear();
  const currentMonth  = new Date().getMonth() + 1; // getMonth() returns 0-11

  const monthNames = [
    "January","February","March","April","May","June",
    "July","August","September","October","November","December"
  ];


  const yearOptions = Array.from({length: 120}, (_, i) =>
    `<option value="${currentYear - i}">${currentYear - i}</option>`).join("");

const monthOptions = monthNames.map((m, index) => `<option value="${index + 1}">${m}</option>`).join("");

  row.innerHTML = `
    <td>${memberCount}</td>
    <td><input type="text" name="member_name_${memberCount}" placeholder="Surname, First name, M.I." required></td>
     <td><input type="number" name="age_${memberCount}" min="0" readonly></td>

    <td><select name="birth_month_${memberCount}" onchange="computeAge(${memberCount})">
        <option value="">Month</option>${monthOptions}
    </select></td>
     <td><select name="birth_year_${memberCount}" onchange="computeAge(${memberCount})">
        <option value="">Year</option>${yearOptions}
    </select></td>
  
    

    <td>
      <select name="sex_${memberCount}">
        <option value="">Select</option>
        <option>Male</option>
        <option>Female</option>
      </select>
    </td>

    <!-- PhilHealth -->
    <td>
      <select name="philhealth_have_${memberCount}" onchange="handlePhilhealth(${memberCount})">
        <option value="">Select</option>
        <option value="Yes">Yes</option>
        <option value="No">No</option>
      </select>
    </td>
    <td><input type="number" name="philhealth_id_${memberCount}" placeholder="PhilHealth ID" style="display:none;"></td>

    <!-- PWD -->
    <td>
      <select name="pwd_have_${memberCount}" onchange="handlePWD(${memberCount})">
        <option value="">Select</option>
        <option value="Yes">Yes</option>
        <option value="No">No</option>
      </select>
    </td>
    <td><input type="number" name="pwd_id_${memberCount}" placeholder="PWD ID" style="display:none;"></td>

    <!-- Relationship -->
    <td>
      <select name="relationship_${memberCount}">
        <option value="">Select</option>
        ${[
          "Head","Spouse","Son","Daughter","Stepson","Stepdaughter","Son in law","Daughter in law",
          "Grandson","Granddaughter","Father","Mother","Brother","Sister","Uncle","Aunt",
          "Nephew","Niece","Other relative","Non-relative","Boarder","Domestic helper"
        ].map(r => `<option value="${r}">${r}</option>`).join("")}
      </select>
    </td>

    <!-- Civil Status -->
    <td>
      <select name="civil_status_${memberCount}">
        <option value="">Select</option>
        <option>Single</option>
        <option>Married</option>
        <option>Living-in</option>
        <option>Widowed</option>
        <option>Separated</option>
        <option>Annuled</option>
        <option>Unknown</option>
      </select>
    </td>

  <td>
  <select name="religion_${memberCount}" required>
    <option value="">Select Religion</option>
    <option value="Roman Catholic">Roman Catholic</option>
    <option value="Christian (Protestant/Born Again)">Christian (Protestant/Born Again)</option>
    <option value="Iglesia ni Cristo">Iglesia ni Cristo</option>
    <option value="Islam">Islam</option>
    <option value="Buddhist">Buddhist</option>
    <option value="Hindu">Hindu</option>
    <option value="Jehovah’s Witness">Jehovah’s Witness</option>
    <option value="Seventh-day Adventist">Seventh-day Adventist</option>
    <option value="Other Christian Denomination">Other Christian Denomination</option>
  </select>
</td>

<td>
  <select name="citizenship_${memberCount}" required>
    <option value="">Select Citizenship</option>
    <option value="Filipino">Filipino</option>
    <option value="Dual Citizen">Dual Citizen</option>
    <option value="Foreign National">Foreign National</option>
  </select>
</td>


    <!-- Education -->
    <td>
      <select name="education_level_${memberCount}" onchange="handleEducation(${memberCount})">
        <option value="">Select</option>
        ${[
          "No education","Pre-school","Elementary level","Elementary graduate","High school level",
          "High school graduate","Junior HS","Junior HS graduate","Senior HS level","Senior HS graduate",
          "Vocational/Tech","College level","College graduate","Post-graduate"
        ].map(e => `<option value="${e}">${e}</option>`).join("")}
      </select>
    </td>
    <td><select name="currently_enrolled_${memberCount}" style="display:none;" onchange="handleEnrollment(${memberCount})">
      <option value="">Select</option>
      <option value="Yes">Yes</option>
      <option value="No">No</option>
    </select></td>
    <td>
      <select name="school_level_${memberCount}" style="display:none;">
        <option value="">Select</option>
        <option>Pre-school</option>
        <option>Elementary</option>
        <option>Junior High School</option>
        <option>Senior High School</option>
        <option>Vocational/Technical</option>
        <option>College/University</option>
      </select>
    </td>
    <td><input type="text" name="school_place_${memberCount}" placeholder="School Name" style="display:none;"></td>

    <!-- Employment -->
    <td>
      <select name="employment_status_${memberCount}" onchange="handleEmployment(${memberCount})">
        <option value="">Select</option>
        <option value="Employed">Employed</option>
        <option value="Unemployed">Unemployed</option>
      </select>
    </td>
    <td>
  <select name="work_details_${memberCount}" style="display:none;">
    <option value="">Select Field of Work</option>
    <option value="Agriculture, Forestry, and Fishing">Agriculture, Forestry, and Fishing</option>
    <option value="Construction">Construction</option>
    <option value="Manufacturing">Manufacturing</option>
    <option value="Wholesale and Retail Trade">Wholesale and Retail Trade</option>
    <option value="Transportation and Storage">Transportation and Storage</option>
    <option value="Accommodation and Food Service">Accommodation and Food Service</option>
    <option value="Information and Communication">Information and Communication</option>
    <option value="Financial and Insurance Activities">Financial and Insurance Activities</option>
    <option value="Real Estate Activities">Real Estate Activities</option>
    <option value="Professional, Scientific, and Technical Services">Professional, Scientific, and Technical Services</option>
    <option value="Administrative and Support Services">Administrative and Support Services</option>
    <option value="Public Administration and Defense">Public Administration and Defense</option>
    <option value="Education">Education</option>
    <option value="Human Health and Social Work">Human Health and Social Work</option>
    <option value="Arts, Entertainment, and Recreation">Arts, Entertainment, and Recreation</option>
    <option value="Other Service Activities">Other Service Activities</option>
    <option value="Overseas Employment (OFW)">Overseas Employment (OFW)</option>
  </select>
</td>


   <td>
  <select name="monthly_income_${memberCount}" style="display:none;">
    <option value="">Select Monthly Income</option>
    <option value="₱5,000 and below">₱5,000 and below</option>
    <option value="₱5,001 - ₱10,000">₱5,001 - ₱10,000</option>
    <option value="₱10,001 - ₱15,000">₱10,001 - ₱15,000</option>
    <option value="₱15,001 - ₱20,000">₱15,001 - ₱20,000</option>
    <option value="₱20,001 and above">₱20,001 and above</option>
    </select>
</td>

    <td><button type="button" onclick="this.closest('tr').remove()">❌</button></td>
  `;

  document.getElementById("householdBody").appendChild(row);
  
});

// =======================
// 🧠 LOGIC HANDLERS
// =======================

// 🧮 Auto-compute age based on birth month + year
function computeAge(i) {
  const birthMonthSelect = document.querySelector(`[name="birth_month_${i}"]`);
  const birthYearSelect = document.querySelector(`[name="birth_year_${i}"]`);
  const ageInput = document.querySelector(`[name="age_${i}"]`);

  const birthMonth = parseInt(birthMonthSelect.value);
  const birthYear = parseInt(birthYearSelect.value);

  if (!birthMonth || !birthYear) {
    ageInput.value = "";
    return;
  }

  const today = new Date();
  const currentYear = today.getFullYear();
  const currentMonth = today.getMonth() + 1;

  let age = currentYear - birthYear;
  if (currentMonth < birthMonth) {
    age--; // birthday not yet reached this year
  }

  ageInput.value = age;
}


function handlePhilhealth(i) {
  const have = document.querySelector(`[name="philhealth_have_${i}"]`).value;
  const idField = document.querySelector(`[name="philhealth_id_${i}"]`);
  idField.style.display = (have === "Yes") ? "inline-block" : "none";
  if (have !== "Yes") idField.value = "";
}

function handlePWD(i) {
  const have = document.querySelector(`[name="pwd_have_${i}"]`).value;
  const idField = document.querySelector(`[name="pwd_id_${i}"]`);
  idField.style.display = (have === "Yes") ? "inline-block" : "none";
  if (have !== "Yes") idField.value = "";
}

function handleEducation(i) {
  const level = document.querySelector(`[name="education_level_${i}"]`).value;
  const enrolled = document.querySelector(`[name="currently_enrolled_${i}"]`);
  const schoolLevel = document.querySelector(`[name="school_level_${i}"]`);
  const schoolPlace = document.querySelector(`[name="school_place_${i}"]`);

  // Hide all first
  enrolled.style.display = "none";
  schoolLevel.style.display = "none";
  schoolPlace.style.display = "none";
  enrolled.value = "";
  schoolLevel.value = "";
  schoolPlace.value = "";

  // ✅ Show "Currently Enrolled" only for students below college graduate
  const showEnrollment = !["No education", "College graduate", "Post-graduate"].includes(level);

  if (showEnrollment) {
    enrolled.style.display = "inline-block";
  }
}


function handleEnrollment(i) {
  const enrolled = document.querySelector(`[name="currently_enrolled_${i}"]`).value;
  const schoolLevel = document.querySelector(`[name="school_level_${i}"]`);
  const schoolPlace = document.querySelector(`[name="school_place_${i}"]`);
  if (enrolled === "Yes") {
    schoolLevel.style.display = "inline-block";
    schoolPlace.style.display = "inline-block";
  } else {
    schoolLevel.style.display = "none";
    schoolPlace.style.display = "none";
    schoolLevel.value = "";
    schoolPlace.value = "";
  }
}

function handleEmployment(i) {
  const status = document.querySelector(`[name="employment_status_${i}"]`).value;
  const workField = document.querySelector(`[name="work_details_${i}"]`);
  const incomeField = document.querySelector(`[name="monthly_income_${i}"]`);
  if (status === "Employed") {
    workField.style.display = "inline-block";
    incomeField.style.display = "inline-block";
  } else {
    workField.style.display = "none";
    incomeField.style.display = "none";
    workField.value = "";
    incomeField.value = "";
  }
}

</script>









<!-- Questions Section -->
<div class="form-section">
    <h2>Questions</h2>


    <!-- ========================== -->
  <!-- NEW: Income and Work Section -->
  <!-- ========================== -->

  <!-- Source of Income -->
  <div class="form-group">
    <label for="income_source">Source of Income: What is the major source of income?</label>
    <select id="income_source" name="income_source" required>
      <option value="">Select Source</option>
      <option value="employment">Employment</option>
      <option value="business">Business</option>
      <option value="remittance">Remittance</option>
      <option value="investments">Investments</option>
    </select>
  </div>

  <!-- Status of Work/Business -->
  <div class="form-group">
    <label for="work_status">Status of Work/Business:</label>
    <select id="work_status" name="work_status" required>
      <option value="">Select Status</option>
      <option value="permanent_work">Permanent work</option>
      <option value="casual_work">Casual work</option>
      <option value="contractual_work">Contractual work</option>
      <option value="individually_owned">Individually owned business</option>
      <option value="shared_partnership">Shared/Partnership business</option>
      <option value="corporate_business">Corporate business</option>
    </select>
  </div>

  <!-- Place of Work/Business -->
  <div class="form-group">
    <label for="work_place">Place of Work/Business (please write the response):</label>
    <input type="text" id="work_place" name="work_place" placeholder="Enter place of work or business">
  </div>
</div>

    <!-- Question 1: Female household member who died -->
    <div class="form-group">
        <label>Do you have any Female household member who died in the past 12 months?</label>
        <select id="female_death" name="female_death" required>
            <option value="">Select an answer</option>
            <option value="no">No</option>
            <option value="yes">Yes</option>
        </select>
    </div>

    <div id="female_death_details" style="display: none;">
        <div class="form-group">
            <label for="female_death_age">How old is she?</label>
            <input type="number" id="female_death_age" name="female_death_age" min="1" max="120" placeholder="Enter age">
        </div>
        <div class="form-group">
            <label for="female_death_cause">What is the cause of her death?</label>
            <input type="text" id="female_death_cause" name="female_death_cause" placeholder="Enter cause of death">
        </div>
    </div>

    <!-- Question 2: Child household member who died -->
    <div class="form-group">
        <label>Do you have a child household member below 5 years old who died in the past 12 months?</label>
        <select id="child_death" name="child_death" required>
            <option value="">Select an answer</option>
            <option value="no">No</option>
            <option value="yes">Yes</option>
        </select>
    </div>

    <div id="child_death_details" style="display: none;">
        <div class="form-group">
            <label for="child_death_age">How old is she/he?</label>
            <input type="number" id="child_death_age" name="child_death_age" min="0" max="5" placeholder="Enter age">
        </div>
        <div class="form-group">
            <label for="child_death_sex">Sex:</label>
            <select id="child_death_sex" name="child_death_sex">
                <option value="">Select Sex</option>
                <option value="female">Female</option>
                <option value="male">Male</option>
            </select>
        </div>
        <div class="form-group">
            <label for="child_death_cause">What is the cause of death?</label>
            <input type="text" id="child_death_cause" name="child_death_cause" placeholder="Enter cause of death">
        </div>
    </div>

    <!-- Question 3: Common diseases -->
    <div class="form-group">
        <label>What are the common diseases that cause death in this barangay? (Provide at least 3)</label>
        <table class="numbered-table">
            <tr>
                <td>1.</td>
                <td><input type="text" name="disease_1" placeholder="Enter disease" required></td>
            </tr>
            <tr>
                <td>2.</td>
                <td><input type="text" name="disease_2" placeholder="Enter disease" required></td>
            </tr>
            <tr>
                <td>3.</td>
                <td><input type="text" name="disease_3" placeholder="Enter disease" required></td>
            </tr>
          
        </table>
    </div>

    <!-- Question 4: Primary needs -->
    <div class="form-group">
        <label>What do you think are the primary needs of this barangay? (Provide at least 3)</label>
        <table class="numbered-table">
            <tr>
                <td>1.</td>
                <td><input type="text" name="need_1" placeholder="Enter primary need" required></td>
            </tr>
            <tr>
                <td>2.</td>
                <td><input type="text" name="need_2" placeholder="Enter primary need" required></td>
            </tr>
            <tr>
                <td>3.</td>
                <td><input type="text" name="need_3" placeholder="Enter primary need" required></td>
            </tr>
            
        </table>
    </div>
</div>

<!-- JavaScript for conditional display -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const femaleSelect = document.getElementById('female_death');
    const femaleDetails = document.getElementById('female_death_details');
    const childSelect = document.getElementById('child_death');
    const childDetails = document.getElementById('child_death_details');

    function toggleDetails(select, details) {
        details.style.display = select.value === 'yes' ? 'block' : 'none';
    }

    femaleSelect.addEventListener('change', () => toggleDetails(femaleSelect, femaleDetails));
    childSelect.addEventListener('change', () => toggleDetails(childSelect, childDetails));
});
</script>

<!-- Add this to your CSS file if not yet present -->
<style>
.numbered-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.numbered-table td:first-child {
    width: 30px;
    text-align: center;
    font-weight: bold;
    color: var(--primary-blue);
}

.numbered-table td:last-child input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}


</style>


<!-- Household Utilities Section -->
<div class="form-section">
    <h2>Household Utilities and Facilities</h2>


<!-- Main Source of Water Supply -->
<div class="form-group">
  <label for="water_supply">What is the household’s main source of drinking water?</label>
  <select id="water_supply" name="water_supply" required>
    <option value="">Select source</option>
    <option value="Lake, river, rain, others">Lake, river, rain, others</option>
    <option value="Dug well">Dug well</option>
    <option value="Unprotected spring">Unprotected spring</option>
    <option value="Protected spring">Protected spring</option>
    <option value="Peddler">Peddler</option>
    <option value="Tubed/Piped shallow well">Tubed/Piped shallow well</option>
    <option value="Shared, tubed/piped deep well">Shared, tubed/piped deep well</option>
    <option value="Own use, tubed/piped deep well">Own use, tubed/piped deep well</option>
    <option value="Shared, faucet community water system">Shared, faucet community water system</option>
    <option value="Own use, faucet community water system">Own use, faucet community water system</option>
    <option value="Bottled water">Bottled water</option>
  </select>
</div>



<!-- Type of Toilet Facilities -->
<div class="form-group">
    <label for="toilet_facility">Type of Toilet Facilities:</label>
    <select id="toilet_facility" name="toilet_facility" required>
        <option value="">Select type</option>
        <option value="none">None</option>
        <option value="open_pit">Open pit</option>
        <option value="closed_pit">Closed pit</option>
        <option value="water_sealed_shared">Water-sealed other depository shared</option>
        <option value="water_sealed_exclusive">Water-sealed other depository exclusive</option>
        <option value="water_sealed_septic_shared">Water-sealed sewer septic tank shared</option>
        <option value="water_sealed_septic_exclusive">Water-sealed sewer septic tank exclusive</option>
    </select>
</div>

<!-- Type of Garbage Disposal -->
<div class="form-group">
    <label for="garbage_disposal">How does your household usually dispose of garbage?</label>
    <select id="garbage_disposal" name="garbage_disposal" required>
        <option value="">Select type</option>
        <option value="burning">Burying</option>
        <option value="composting">Composting</option>
        <option value="burying">Burning</option>
        <option value="segregation">Dumping individual pit (not burned)</option>
        <option value="burying">Picked-up by garbage truck</option>
    </select>
</div>

<!-- segregate-->
<div class="form-group">
    <label for="segregate">Do you segregate your garbage?</label>
    <select id="segregate" name="segregate" required>
        <option value="">Select type</option>
        <option value="yes">Yes</option>
        <option value="no">No</option>
    </select>
</div>

<!-- Lighting Fuel -->
<div class="form-group">
    <label for="lighting_fuel">What type of fuel does this household use for lighting?</label>
    <select id="lighting_fuel" name="lighting_fuel" required>
        <option value="">Select type</option>
        <option value="none">None</option>
        <option value="oil">Oil (vegetable, animal, others)</option>
        <option value="lpg">Liquified Petroleum Gas (LPG)</option>
        <option value="kerosene">Kerosene</option>
        <option value="electricity">Electricity</option>
        <option value="other">Others (specify)</option>
    </select>
    <input type="text" id="lighting_other" name="lighting_other" placeholder="Please specify" style="display: none;">
</div>

<!-- Cooking Fuel -->
<div class="form-group">
    <label for="cooking_fuel">What kind of fuel does this household use most of the time for cooking?</label>
    <select id="cooking_fuel" name="cooking_fuel" required>
        <option value="">Select type</option>
        <option value="none">None</option>
        <option value="wood">Wood</option>
        <option value="charcoal">Charcoal</option>
        <option value="lpg">Liquified Petroleum Gas (LPG)</option>
        <option value="kerosene">Kerosene</option>
        <option value="electricity">Electricity</option>
        <option value="other">Others (specify)</option>
    </select>
    <input type="text" id="cooking_other" name="cooking_other" placeholder="Please specify" style="display: none;">
 </div>


   <div class="form-actions">
        <button type="submit">Submit Census Form</button>
    </div>
    
</form>

<!-- JavaScript for "Others specify" behavior -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selects = [
        { selectId: 'toilet_facility', inputId: 'toilet_other' },
        { selectId: 'lighting_fuel', inputId: 'lighting_other' },
        { selectId: 'cooking_fuel', inputId: 'cooking_other' }
    ];

    selects.forEach(item => {
        const select = document.getElementById(item.selectId);
        const input = document.getElementById(item.inputId);

        select.addEventListener('change', function() {
            if (select.value === 'other') {
                input.style.display = 'block';
            } else {
                input.style.display = 'none';
                input.value = '';
            }
        });
    });
});
</script>

<?php
// ✅ Fetch barangay and purok data from DB
$streetsData = [];
try {
    $stmt = $conn->query("SELECT barangay_name, purok_names FROM barangay_registration");
    while ($row = $stmt->fetch_assoc()) {
        $barangay = $row['barangay_name'];
        $puroks = array_map('trim', explode(',', $row['purok_names']));
        $streetsData[$barangay] = $puroks;
    }
} catch (Exception $e) {
    $streetsData = [];
}
?>

<script>
    // 🧩 Dynamic Street Dropdown based on Barangay
    const barangaySelect = document.getElementById("barangay");
    const streetSelect = document.getElementById("street");

    // ✅ Dynamically generated from PHP
    const streets = <?php echo json_encode($streetsData, JSON_PRETTY_PRINT); ?>;

    barangaySelect.addEventListener("change", function() {
        const selectedBarangay = this.value;
        streetSelect.innerHTML = '<option value="">Select Street</option>';

        if (streets[selectedBarangay]) {
            streets[selectedBarangay].forEach(street => {
                const option = document.createElement("option");
                option.value = street;
                option.textContent = street;
                streetSelect.appendChild(option);
            });
        }
    });
</script>

</body>
</html>