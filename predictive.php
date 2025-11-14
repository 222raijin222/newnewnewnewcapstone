<?php
session_start();
require_once 'config.php';


error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// ----------------------------
// 1️⃣ Get selected barangay
// ----------------------------
$selected_barangay = $_GET['barangay'] ?? '';
if (empty($selected_barangay)) {
    if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        echo json_encode(["error" => "Missing barangay parameter"]);
        exit;
    } else {
        $selected_barangay = ''; // allow UI to show input form
    }
}

// ----------------------------
// 2️⃣ Fetch barangay info
// ----------------------------
$barangay = null;
if (!empty($selected_barangay)) {
    $stmt = $conn->prepare("SELECT * FROM barangay_registration WHERE barangay_name = ? LIMIT 1");
    $stmt->bind_param("s", $selected_barangay);
    $stmt->execute();
    $barangay = $stmt->get_result()->fetch_assoc();

    if (!$barangay) {
        echo json_encode(['error' => 'Barangay not found']);
        exit;
    }
}

// ----------------------------
// 🌦️ 3️⃣ Fetch Weather Forecast (PAGASA + fallback)
// ----------------------------
$pagasa_url = "https://api.pagasa.dost.gov.ph/weather/pampanga";
$weather_data = @json_decode(file_get_contents($pagasa_url), true);

// Fallback simulated data if API not reachable
if (!$weather_data || !isset($weather_data['rainfall'])) {
    $weather_data = [
        "rainfall" => [200, 250, 300, 400, 450, 500, 550, 480, 350, 250, 200, 150],
        "temperature" => [30, 31, 33, 34, 35, 36, 35, 34, 33, 32, 31, 30]
    ];
}

$avg_rainfall = array_sum($weather_data['rainfall']) / count($weather_data['rainfall']);
$avg_temp = array_sum($weather_data['temperature']) / count($weather_data['temperature']);

// Weather-based risk indicators
$dengue_risk  = min(100, ($avg_rainfall / 500) * 100);
$flood_risk   = min(100, ($avg_rainfall / 550) * 100);
$heat_risk    = max(0, (($avg_temp - 30) / 10) * 100);
$drought_risk = max(0, (1 - ($avg_rainfall / 500)) * 100);

// ----------------------------
// 4️⃣ Fetch census + members
// ----------------------------
$census_stmt = $conn->prepare("SELECT * FROM census_submissions WHERE barangay = ?");
$census_stmt->bind_param("s", $selected_barangay);
$census_stmt->execute();
$census_results = $census_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$household_members = [];
if ($census_results) {
    $census_ids = array_column($census_results, 'id');
    $placeholders = implode(',', array_fill(0, count($census_ids), '?'));
    $types = str_repeat('i', count($census_ids));
    $stmt = $conn->prepare("SELECT * FROM household_members WHERE household_id IN ($placeholders)");
    $stmt->bind_param($types, ...$census_ids);
    $stmt->execute();
    $household_members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$total_households = (int)($barangay['total_households'] ?? 0);
$total_population = (int)($barangay['total_population'] ?? 0);

// ----------------------------
// 5️⃣ Weighted Socioeconomic Analysis
// ----------------------------
$household_income = [];
foreach ($household_members as $member) {
    if (!empty($member['monthly_income']) && !empty($member['household_id'])) {
        $income_text = strtolower(trim($member['monthly_income']));
        $id = $member['household_id'];
        if (strpos($income_text, '₱5,000 and below') !== false) $income_value = 5000;
        elseif (strpos($income_text, '₱5,001 - ₱10,000') !== false) $income_value = 10000;
        elseif (strpos($income_text, '₱10,001 - ₱15,000') !== false) $income_value = 15000;
        elseif (strpos($income_text, '₱15,001 - ₱20,000') !== false) $income_value = 20000;
        elseif (strpos($income_text, '₱20,001 and above') !== false) $income_value = 25000;
        else $income_value = 0;

        $household_income[$id][] = $income_value;
    }
}

$total_people_in_low_income_households = 0;
$total_population_from_households = 0;
foreach ($household_income as $household_id => $incomes) {
    $average_income = array_sum($incomes) / max(1, count($incomes));
    $household_size = count(array_filter($household_members, fn($m) => $m['household_id'] == $household_id));
    $total_population_from_households += $household_size;
    if ($average_income <= 10000) $total_people_in_low_income_households += $household_size;
}

$low_income_rate = ($total_population_from_households > 0)
    ? ($total_people_in_low_income_households / $total_population_from_households) * 100
    : 0;

// ----------------------------
// 6️⃣ Waste & Health Data
// ----------------------------
$total_people_in_poor_waste = 0;
$total_people_with_health_issues = 0;
foreach ($census_results as $row) {
    $household_id = $row['id'];
    $household_size = count(array_filter($household_members, fn($m) => $m['household_id'] == $household_id));

    $garbage_disposal = strtolower(trim($row['garbage_disposal'] ?? ''));
    $segregate = strtolower(trim($row['segregate'] ?? ''));
    $poor_methods = ['burning', 'burying', 'dumping', 'none', 'open burning'];
    if (in_array($garbage_disposal, $poor_methods) || $segregate === 'no')
        $total_people_in_poor_waste += $household_size;

    if (!empty($row['disease_1']) || !empty($row['disease_2']) || !empty($row['disease_3']))
        $total_people_with_health_issues += $household_size;
}

$waste_problem_rate = ($total_population_from_households > 0)
    ? ($total_people_in_poor_waste / $total_population_from_households) * 100
    : 0;

$health_issue_rate = ($total_population_from_households > 0)
    ? ($total_people_with_health_issues / $total_population_from_households) * 100
    : 0;

// ----------------------------
// 7️⃣ Youth Analysis
// ----------------------------
$total_youth = 0;
$total_enrolled_youth = 0;
foreach ($household_members as $member) {
    $age = (int)($member['age'] ?? 0);
    if ($age >= 5 && $age <= 24) {
        $total_youth++;
        if (trim($member['currently_enrolled'] ?? '') === 'Yes')
            $total_enrolled_youth++;
    }
}
$youth_rate = ($total_youth > 0) ? ($total_enrolled_youth / $total_youth) * 100 : 0;

$flood_prone = strtolower($barangay['flood_prone'] ?? '') === 'yes';

// ----------------------------
// 8️⃣ Integrate Rules + Weather
// ----------------------------
$rules_query = $conn->query("SELECT * FROM event_rules");
$results = [];

while ($rule = $rules_query->fetch_assoc()) {
    $event = $rule['event_name'];
    $condition = strtolower($rule['rule_condition']);
    $score = 0;

    // Existing conditions
    if (strpos($condition, 'flood_prone') !== false && $flood_prone) $score += $rule['score'];
    if (strpos($condition, 'low_income') !== false && $low_income_rate > 30) $score += $rule['score'];
    if (strpos($condition, 'waste') !== false && $waste_problem_rate > 20) $score += $rule['score'];
    if (strpos($condition, 'disease') !== false && $health_issue_rate > 10) $score += $rule['score'];
    if (strpos($condition, 'youth') !== false && $youth_rate > 20) $score += $rule['score'];

 


    // 🌦️ NEW weather-based factors
    if (strpos($condition, 'rain') !== false && $avg_rainfall > 450) $score += $rule['score'];
    if (strpos($condition, 'heat') !== false && $avg_temp > 34) $score += $rule['score'];
    if (strpos($condition, 'drought') !== false && $avg_rainfall < 200) $score += $rule['score'];

    $results[$event] = ($results[$event] ?? 0) + $score;
}

// Normalize prediction scores
$max_score = $results ? max($results) : 0;
$predictions = [];
foreach ($results as $event => $score) {
    $normalized = ($max_score > 0) ? round(($score / $max_score) * 100, 2) : 0;
    if ($normalized >= 50) {
        $predictions[] = [
            'event' => $event,
            'score' => $normalized,
            'reason' => 'Based on socioeconomic and weather risk indicators',
            'description' => $rule['description'] ?? '' // Fetch description from DB
        ];
    }
}

// ----------------------------
// 9️⃣ Summary
// ----------------------------
$summary = [
    'total_population' => $total_population,
    'total_households' => $total_households,
    'low_income_rate' => round($low_income_rate, 2) . '%',
    'waste_problem_rate' => round($waste_problem_rate, 2) . '%',
    'health_issue_rate' => round($health_issue_rate, 2) . '%',
    'youth_enrollment_rate' => round($youth_rate, 2) . '%',
    'flood_prone' => $flood_prone ? 'Yes' : 'No',
    'annual_budget' => $barangay['annual_budget'] ?? 0,
    'avg_rainfall_mm' => round($avg_rainfall, 1),
    'avg_temperature_c' => round($avg_temp, 1),
    'dengue_risk' => round($dengue_risk, 1) . '%',
    'flood_risk' => round($flood_risk, 1) . '%',
    'heat_risk' => round($heat_risk, 1) . '%',
    'drought_risk' => round($drought_risk, 1) . '%'
];

// ----------------------------
// 🔟 Output
// ----------------------------
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'barangay' => $selected_barangay,
        'summary' => $summary,
        'predictions' => $predictions
    ], JSON_PRETTY_PRINT);
    exit;
}

// Otherwise show HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Predictive Analytics - <?= htmlspecialchars($selected_barangay) ?></title>
<link rel="stylesheet" href="predictive.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {font-family: Arial, sans-serif; background:#f5f6fa; margin:0; padding:20px;}
.container {background:white; padding:20px; border-radius:10px; max-width:900px; margin:auto; box-shadow:0 0 8px rgba(0,0,0,0.1);}
h1 {color:#1e3a8a; text-align:center;}
table {width:100%; border-collapse:collapse; margin-top:20px;}
th,td {padding:10px; border-bottom:1px solid #ddd; text-align:center;}
th {background:#1e40af; color:white;}
tr:hover {background:#f1f5f9;}
.summary {background:#e0f2fe; padding:10px; border-radius:8px;}
</style>
</head>
<body>
<div class="container">
    <h1>📊 Predictive Analytics - <?= htmlspecialchars($selected_barangay) ?></h1>
    <div class="summary">
        <strong>Total Population:</strong> <?= $summary['total_population'] ?><br>
        <strong>Total Households:</strong> <?= $summary['total_households'] ?><br>
        <strong>Low Income Rate:</strong> <?= $summary['low_income_rate'] ?><br>
        <strong>Waste Problem Rate:</strong> <?= $summary['waste_problem_rate'] ?><br>
        <strong>Health Issue Rate:</strong> <?= $summary['health_issue_rate'] ?><br>
        <strong>Youth Enrollment Rate:</strong> <?= $summary['youth_enrollment_rate'] ?><br>
        <strong>Flood Prone:</strong> <?= $summary['flood_prone'] ?><br>
        <strong>Average Rainfall:</strong> <?= $summary['avg_rainfall_mm'] ?> mm<br>
        <strong>Average Temperature:</strong> <?= $summary['avg_temperature_c'] ?> °C<br>
        <strong>Dengue Risk:</strong> <?= $summary['dengue_risk'] ?><br>
        <strong>Flood Risk:</strong> <?= $summary['flood_risk'] ?><br>
        <strong>Heat Risk:</strong> <?= $summary['heat_risk'] ?><br>
        <strong>Drought Risk:</strong> <?= $summary['drought_risk'] ?><br>
        <strong>Annual Budget:</strong> ₱<?= number_format($summary['annual_budget']) ?><br>
    </div>
     <div class="weather-card">
    <h2>🌤️ Weather Risk Forecast</h2>
    <h4>Weather-Based Monthly Risk Forecast</h4>
    <div id="riskModal" class="modal">
  <div class="modal-content">
    <span class="close-btn">&times;</span>
    <div class="modal-header">Risk Details</div>
    <div class="risk-item"><strong>Rainfall:</strong> <span id="modalRainfall"></span> mm</div>
    <div class="risk-item"><strong>Temperature:</strong> <span id="modalTemperature"></span> °C</div>
    <div class="risk-item"><strong>Main Risk:</strong> <span id="modalMainRisk"></span></div>
    <div class="risk-item"><strong>Recommendation:</strong> <span id="modalRecommendation"></span></div>
  </div>
</div>

    <div class="chart-container">
      <canvas id="weatherChart"></canvas>
    </div>
  </div>

    <h2>Predicted Priority Events</h2>
    <table>
        <thead>
            <tr>
                <th>Event</th>
                <th>Score (%)</th>
                <th>Approximate Budget (₱)</th>
                <th>Reason</th>
            </tr>
        </thead>
       <tbody>
<?php
usort($predictions, fn($a, $b) => $b['score'] <=> $a['score']);
$annualBudget = (float)$summary['annual_budget'];

foreach ($predictions as $p):
    // Calculate an approximate budget: 20%-60% of annual budget as a sample
    $minPercent = 0.2; // 20% of annual budget
    $maxPercent = 0.6; // 60% of annual budget
    $approxBudget = round($annualBudget * ($minPercent + (($maxPercent - $minPercent) * ($p['score']/100))), -3);

?>
<tr>
    <td><?= htmlspecialchars($p['event']) ?></td>
    <td><strong><?= $p['score'] ?>%</strong></td>
    <td>₱<?= number_format($approxBudget) ?></td>
    <td><?= htmlspecialchars($p['reason']) ?></td>
    <td><?= htmlspecialchars($p['description']) ?></td>
</tr>
<?php endforeach; ?>

</tbody>

    </table>
</div>
</body>

<script>
// ✅ Monthly weather data
const monthlyLabels = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
const rainfallData = [200, 250, 300, 400, 450, 500, 550, 480, 350, 250, 200, 150];
const temperatureData = [30, 31, 33, 34, 35, 36, 35, 34, 33, 32, 31, 30];

// Risk levels as percentage
const dengueRisk = [40, 50, 60, 70, 68, 65, 60, 55, 50, 45, 40, 35];
const floodRisk = [30, 35, 40, 50, 61.8, 55, 50, 45, 40, 35, 30, 25];
const heatRisk = [0, 10, 20, 25, 28.3, 30, 28, 25, 20, 15, 10, 5];
const droughtRisk = [60, 55, 50, 40, 32, 30, 28, 25, 30, 35, 40, 45];

const ctx = document.getElementById('weatherChart').getContext('2d');

const weatherChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: monthlyLabels,
    datasets: [
      { label: 'Dengue/Flood Risk', data: dengueRisk, borderColor: '#e11d48', fill: true, tension: 0.4 },
      { label: 'Heat Risk', data: heatRisk, borderColor: '#facc15', fill: true, tension: 0.4 },
      { label: 'Drought Risk', data: droughtRisk, borderColor: '#0d9488', fill: true, tension: 0.4 }
    ]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'top' },
      tooltip: { mode: 'index', intersect: false }
    },
    interaction: { mode: 'nearest', axis: 'x', intersect: false },
    scales: {
      y: { beginAtZero: true, max: 100, title: { display: true, text: 'Risk Level (%)' } }
    },
    onClick: (evt, elements) => {
      if (!elements.length) return;
      const idx = elements[0].index;

      const modal = document.getElementById('riskModal');
      document.getElementById('modalRainfall').innerText = rainfallData[idx];
      document.getElementById('modalTemperature').innerText = temperatureData[idx];

      // Decide main risk
      const risks = [
        {name: 'Dengue/Flood', value: dengueRisk[idx]},
        {name: 'Heat', value: heatRisk[idx]},
        {name: 'Drought', value: droughtRisk[idx]}
      ];
      risks.sort((a,b) => b.value - a.value);
      document.getElementById('modalMainRisk').innerText = risks[0].name;
      document.getElementById('modalRecommendation').innerText = 'Normal monitoring.';

      modal.style.display = 'flex';
    }
  }
});

// Close modal
document.querySelector('.close-btn').onclick = () => {
  document.getElementById('riskModal').style.display = 'none';
};
window.onclick = (e) => {
  if (e.target.id === 'riskModal') document.getElementById('riskModal').style.display = 'none';
};
</script>

</html>
