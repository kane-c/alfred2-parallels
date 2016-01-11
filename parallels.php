<?php
// Main configuration
$vm = isset($_SERVER['argv'][1]) ? trim($_SERVER['argv'][1]) : '';

$supportedActions = array(
    'start' => array('running', 'suspended', 'paused'),
    'stop' => array('stopped'),
    'reset' => array('stopped', 'suspended'),
    'suspend' => array('stopped', 'suspended'),
    'resume' => array('running', 'stopped'),
    'pause' => array('paused', 'suspended', 'stopped'),
);

$vms = array();
$results = array();

// Read VM lists
$vmEscaped = escapeshellarg($vm);
exec("/usr/local/bin/prlctl list -a -j $vmEscaped 2>&1", $output, $exitCode);
$output = implode($output, "\n");

if ($exitCode !== 0) {
    $xmlObject = new SimpleXMLElement('<items></items>');
    $nodeObject = $xmlObject->addChild('item');
    $nodeObject->addChild('title', $output);
    $nodeObject->addAttribute('valid', 'no');

    echo $xmlObject->asXML();
    die;
} elseif ($output) {
    $vms = json_decode($output);
}

date_default_timezone_set('Australia/Melbourne');

if ($vm && $vms) {
    // Action lists per VM
    foreach ($supportedActions as $action => $currentStatus) {
        if (!in_array($vms[0]->status, $currentStatus)) {
            $results[] = array(
                'uid' => $vms[0]->name . ':' . $action,
                'arg' => $action . ' ' . escapeshellarg($vm),
                'title' => ucfirst($action) . ' ' . $vms[0]->name,
                'icon' => 'icon.png',
                'valid' => 'yes',
            );
        }
    }

    if ($vms[0]->status === 'running') {
        $results[] = array(
            'uid' => $vms[0]->name . ':' . 'capture',
            'arg' => 'capture ' . $vm . ' --file ~/Desktop/' . str_replace(array(' ', '/'), array('-', '-'), $vms[0]->name) . '-' . date('Ymd-his') . '.jpg',
            'title' => 'Capture a screenshot',
            'subtitle' => $vms[0]->name,
            'icon' => 'icon.png',
            'valid' => 'yes',
        );
    }
} else {
    // List of VM matched
    foreach ($vms as $vm) {
        $results[] = array(
            'uid' => $vm->uuid,
            'arg' => $vm->uuid,
            'title' => $vm->name,
            'subtitle' => ucfirst($vm->status),
            'icon' => 'icon.png',
            'valid' => 'no',
            'autocomplete' => $vm->name,
        );
    }
}

// No VM matched
if (!$results) {
    $results[] = array(
        'uid' => '',
        'arg' => 'none',
        'title' => 'No matching VMs',
        'icon' => 'icon.png',
        'valid' => 'no',
    );
}

// Preparing the XML output file
$xmlObject = new SimpleXMLElement('<items></items>');
$xmlAttributes = array('uid', 'arg', 'valid', 'autocomplete');

foreach ($results as $rows) {
    $nodeObject = $xmlObject->addChild('item');
    $nodeKeys = array_keys($rows);

    foreach ($nodeKeys as $key) {
        $nodeObject->{in_array($key, $xmlAttributes) ? 'addAttribute' : 'addChild'}($key, $rows[$key]);
    }
}

// Print the XML output
echo $xmlObject->asXML();
