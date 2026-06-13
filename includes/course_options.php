<?php
$selected_course = $selected_course ?? '';
$options = [
    'School of Science and Engineering' => [
        'CSE' => 'B.Sc. in Computer Science and Engineering (CSE)',
        'EEE' => 'B.Sc. in Electrical and Electronic Engineering (EEE)',
        'CE' => 'B.Sc. in Civil Engineering (CE)',
        'BSDS' => 'B.Sc. in Data Science (BSDS)'
    ],
    'School of Business and Economics' => [
        'BBA' => 'Bachelor of Business Administration (BBA)',
        'BBA in AIS' => 'BBA in Accounting Information Systems (BBA in AIS)',
        'BSECO' => 'Bachelor of Science in Economics (BSECO)'
    ],
    'School of Humanities and Social Sciences' => [
        'BA in English' => 'Bachelor of Arts in English (BA in English)',
        'BSSMSJ' => 'Bachelor of Social Science in Media Studies and Journalism (BSSMSJ)',
        'BSSEDS' => 'Bachelor of Social Science in Environment and Development Studies (BSSEDS)'
    ],
    'School of Life Sciences' => [
        'B.Pharm' => 'Bachelor of Pharmacy (B.Pharm)',
        'BSBGE' => 'B.Sc. in Biotechnology & Genetic Engineering (BSBGE)'
    ]
];

echo '<option value="">Select your program...</option>';
foreach ($options as $group => $courses) {
    echo '<optgroup label="' . htmlspecialchars($group) . '">';
    foreach ($courses as $val => $label) {
        $selected = ($selected_course === $val) ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($val) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
    echo '</optgroup>';
}
?>
