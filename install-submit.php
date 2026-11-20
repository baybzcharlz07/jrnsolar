<?php
require __DIR__.'/campaign-common.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit('Please use the solar inquiry form.'); }
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 12000) { http_response_code(413); exit('Request too large.'); }
function val(string $k): string { return isset($_POST[$k]) && is_string($_POST[$k]) ? trim($_POST[$k]) : ''; }
function fail_form(string $message): void {
    $_SESSION['form_error']=$message; $_SESSION['form_old']=[];
    foreach (['name','phone','city','bill','property','system'] as $key) { $_SESSION['form_old'][$key]=substr(val($key),0,160); }
    go('/installnow#solar-quote');
}
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'],val('csrf'))) { fail_form('Your form session expired. Please check your details and submit again.'); }
$submission=val('submission_id');
if (!preg_match('/^[a-f0-9]{32}$/',$submission) || !hash_equals((string)($_SESSION['submission_id'] ?? ''),$submission)) { fail_form('Please reload the form and try again.'); }
if (!empty($_SESSION['last_submission']) && $_SESSION['last_submission'] === $submission) { go('/installnow/thank-you'); }
if (val('website') !== '') { fail_form('We couldn’t accept this request. Please try again.'); }
$name=val('name');$city=val('city');$phone=preg_replace('/[ ()-]/','',val('phone'));$bill=val('bill');$property=val('property');$system=val('system');
if (strlen($name)<2 || strlen($name)>80 || preg_match('/[\x00-\x1F]/',$name)) { fail_form('Please enter your full name.'); }
if (!preg_match('/^(?:09[0-9]{9}|\+?639[0-9]{9})$/',$phone)) { fail_form('Please enter a valid Philippine mobile number, such as 09XX XXX XXXX.'); }
if (strlen($city)<2 || strlen($city)>120 || preg_match('/[\x00-\x1F]/',$city)) { fail_form('Please enter your property’s city or municipality.'); }
if (!in_array($bill,['Below ₱3,000','₱3,000–₱5,000','₱5,001–₱10,000','₱10,001–₱20,000','Above ₱20,000'],true)) { fail_form('Please select your monthly electricity bill range.'); }
if (!in_array($property,['','Home','Business','Other'],true) || !in_array($system,['','6 kW','8 kW','12 kW','Larger system'],true)) { fail_form('Please check your property and system selections.'); }
if (val('consent') !== 'yes') { fail_form('Please agree that JRN may contact you about this inquiry.'); }
$reference='JRN-'.strtoupper(bin2hex(random_bytes(6)));
$lead=['reference'=>$reference,'submission_id'=>$submission,'created_at'=>gmdate('c'),'source'=>'installnow','name'=>$name,'phone'=>$phone,'city'=>$city,'bill'=>$bill,'property'=>$property,'system'=>$system,'consent'=>true,'consent_version'=>'2026-11-19','campaign'=>$_SESSION['campaign_tags'] ?? []];
try { $reference=store_lead($lead); }
catch (Throwable $e) {
    error_log('JRN campaign: '.($e->getMessage()==='RATE_LIMIT'?'rate limited':'storage unavailable'));
    fail_form($e->getMessage()==='RATE_LIMIT'?'You’ve sent several inquiries recently. Please wait 15 minutes before trying again.':'We couldn’t save your inquiry just now. Please try again, or contact JRN at 0966 805 3179.');
}
$_SESSION['last_submission']=$submission;
$_SESSION['receipt']=['reference'=>$reference,'created_at'=>time()];
$_SESSION['lead_event_pending']=true;
unset($_SESSION['form_old'],$_SESSION['form_error']);
go('/installnow/thank-you');
