#!/usr/bin/php -q
<?php
// FreePBX Bootstrap Environment
include_once('/etc/freepbx.conf');

$dsn = "mysql:host=localhost;dbname=denycall";
$username = $amp_conf['AMPDBUSER'];
$password = $amp_conf['AMPDBPASS'];

// set up AGI environment
include("phpagi.php");
$agi = new AGI();

$agi->verbose("DenyCall Checker Started");
$agi->exec('Playback', 'custom/HDK_DenyCallChecker_Started_BGM');
$agi->exec('Playback', 'custom/HDK_DenyCallChecker_Started');
$agi->sleep(3);

$callerId_V = $agi->get_variable('CALLERID(num)');
$callerId = $callerId_V['data'];
$agi->verbose("Check Caller ID: $callerId");

if ($callerId == "Anonymous") {
    $agi->exec('Playback', 'custom/HDK_DenyCallChecker_Denied_BGM');
    $agi->exec('Playback', 'custom/HDK_DenyCallChecker_Denied_Reason_Anonymous');
    $agi->verbose("Caller ID $callerId is denied");
    $agi->exec('Hangup');
    exit();
}else{
    try {
        $pdo = new PDO($dsn, $username, $password);
        $sql = "SELECT * FROM denycid WHERE callerId = '$callerId'";
        $sth = $pdo->prepare($sql);
        $sth->execute();
        $result = $sth->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $agi->exec('Playback', 'custom/HDK_DenyCallChecker_Denied_BGM');
            $agi->exec('Playback', 'custom/HDK_DenyCallChecker_Denied_1');
            $agi->verbose("Caller ID $callerId is denied");
            switch ($result['reasonCode']) {
                case 1:
                    // 総合的判断
                    $agi->exec('Playback', 'custom/HDK_DenyCallChecker_Denied_Reason_1');
                    break;
                case 2:
                    // 複数回コール
                    $agi->exec('Playback', 'custom/HDK_DenyCallChecker_Denied_Reason_2');
                    break;
                case 3:
                    // 一時的ロック
                    $agi->exec('Playback', 'custom/HDK_DenyCallChecker_Denied_Reason_3');
                    break;
            }
            $agi->exec('Playback', 'custom/HDK_DenyCallChecker_Denied_99');
            $agi->exec('Hangup');
        } else {
            $agi->exec('Playback', 'custom/HDK_DenyCallChecker_Allowed_BGM');
            $agi->verbose("Caller ID $callerId is allowed");
            $agi->exec('Playback', 'custom/HDK_DenyCallChecker_Allowed');
        }
    } catch (PDOException $e) {
        $agi->verbose("Error: " . $e->getMessage());
        $agi->exec('Playback', 'custom/HDK_DenyCallChecker_Error');
        $agi->exec('Hangup');
        exit();
    }
}
