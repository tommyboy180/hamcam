<?php
/**
 * onvif_proxy.php
 * Translates PTZ/ONVIF commands from the browser into SOAP calls
 * against the Tapo C210's ONVIF endpoint.
 *
 * The C210 supports ONVIF Profile S (PTZ).
 * ONVIF endpoint: http://<cam>:2020/onvif/device_service
 *
 * Note: Tapo C210 Pan/Tilt is motorised (360° pan, 114° tilt).
 * Zoom is digital only.
 */
require_once 'config.php';
require_once 'setup_guard.php';
session_start();

header('Content-Type: application/json');

if (empty($_SESSION['hamcam_auth'])) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Forbidden']);
    exit;
}

$raw    = file_get_contents('php://input');
$body   = json_decode($raw, true);
$action = $body['action'] ?? '';

$onvif_base = sprintf('http://%s:%d/onvif', ONVIF_HOST, ONVIF_PORT);

/**
 * Build a basic WS-UsernameToken SOAP header.
 * Tapo accepts plain-text credentials for local ONVIF.
 */
function onvif_header(): string {
    $nonce   = base64_encode(random_bytes(16));
    $created = gmdate('Y-m-d\TH:i:s\Z');
    $digest  = base64_encode(sha1(base64_decode($nonce) . $created . CAMERA_PASS, true));
    return <<<XML
<s:Header>
  <Security xmlns="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
    <UsernameToken>
      <Username>{$_u}</Username>
      <Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest">{$digest}</Password>
      <Nonce EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary">{$nonce}</Nonce>
      <Created xmlns="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-utility-1.0.xsd">{$created}</Created>
    </UsernameToken>
  </Security>
</s:Header>
XML;
}

function onvif_soap(string $service, string $body_xml): string|false {
    global $onvif_base;
    $user    = CAMERA_USER;
    $pass    = CAMERA_PASS;
    $nonce   = base64_encode(random_bytes(16));
    $created = gmdate('Y-m-d\TH:i:s\Z');
    $digest  = base64_encode(sha1(base64_decode($nonce) . $created . $pass, true));

    $envelope = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"
            xmlns:pt="http://www.onvif.org/ver20/ptz/wsdl"
            xmlns:tt="http://www.onvif.org/ver10/schema"
            xmlns:d="http://www.onvif.org/ver10/device/wsdl">
  <s:Header>
    <Security xmlns="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
      <UsernameToken>
        <Username>$user</Username>
        <Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest">$digest</Password>
        <Nonce EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary">$nonce</Nonce>
        <Created xmlns="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-utility-1.0.xsd">$created</Created>
      </UsernameToken>
    </Security>
  </s:Header>
  <s:Body>$body_xml</s:Body>
</s:Envelope>
XML;

    $url = $onvif_base . '/' . ltrim($service, '/');
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/soap+xml; charset=utf-8\r\n",
            'content'       => $envelope,
            'timeout'       => 4,
            'ignore_errors' => true,
        ]
    ]);
    return @file_get_contents($url, false, $ctx);
}

// Profile token – C210 typically uses "Profile_1"
$PROFILE = 'Profile_1';

$result = ['ok' => true];

switch ($action) {

    case 'move':
        $pan   = floatval($body['pan']   ?? 0);
        $tilt  = floatval($body['tilt']  ?? 0);
        $speed = max(1, min(10, intval($body['speed'] ?? 5)));
        $s     = round($speed / 10, 2);
        $vel_pan   = round($pan  * $s, 3);
        $vel_tilt  = round($tilt * $s, 3);

        $soap_body = <<<XML
<pt:ContinuousMove>
  <pt:ProfileToken>$PROFILE</pt:ProfileToken>
  <pt:Velocity>
    <tt:PanTilt x="$vel_pan" y="$vel_tilt"/>
  </pt:Velocity>
</pt:ContinuousMove>
XML;
        onvif_soap('ptz_service', $soap_body);

        // Stop after 400ms (simulates a step-move)
        usleep(400000);
        $stop_body = <<<XML
<pt:Stop>
  <pt:ProfileToken>$PROFILE</pt:ProfileToken>
  <pt:PanTilt>true</pt:PanTilt>
</pt:Stop>
XML;
        onvif_soap('ptz_service', $stop_body);
        break;

    case 'zoom':
        $zoom  = floatval($body['zoom']  ?? 0);
        $speed = max(1, min(10, intval($body['speed'] ?? 5)));
        $s     = round($speed / 10, 2);
        $vel_z = round($zoom * $s, 3);

        $soap_body = <<<XML
<pt:ContinuousMove>
  <pt:ProfileToken>$PROFILE</pt:ProfileToken>
  <pt:Velocity>
    <tt:Zoom x="$vel_z"/>
  </pt:Velocity>
</pt:ContinuousMove>
XML;
        onvif_soap('ptz_service', $soap_body);
        usleep(400000);
        $stop_body = <<<XML
<pt:Stop>
  <pt:ProfileToken>$PROFILE</pt:ProfileToken>
  <pt:Zoom>true</pt:Zoom>
</pt:Stop>
XML;
        onvif_soap('ptz_service', $stop_body);
        break;

    case 'home':
        $soap_body = <<<XML
<pt:GotoHomePosition>
  <pt:ProfileToken>$PROFILE</pt:ProfileToken>
</pt:GotoHomePosition>
XML;
        onvif_soap('ptz_service', $soap_body);
        break;

    case 'preset_save':
        $soap_body = <<<XML
<pt:SetPreset>
  <pt:ProfileToken>$PROFILE</pt:ProfileToken>
  <pt:PresetName>HamCAM_Home</pt:PresetName>
</pt:SetPreset>
XML;
        onvif_soap('ptz_service', $soap_body);
        break;

    case 'preset_load':
        $soap_body = <<<XML
<pt:GotoPreset>
  <pt:ProfileToken>$PROFILE</pt:ProfileToken>
  <pt:PresetToken>1</pt:PresetToken>
</pt:GotoPreset>
XML;
        onvif_soap('ptz_service', $soap_body);
        break;

    case 'reboot':
        $soap_body = '<d:SystemReboot/>';
        onvif_soap('device_service', $soap_body);
        break;

    default:
        $result = ['ok' => false, 'error' => 'Unknown action'];
}

echo json_encode($result);