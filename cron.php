<?php

require 'config.inc.php';
require 'vendor/autoload.php';

$base_path = "D:/Gregoire/ICAM/PayIcam/Backups/";
$filename = 'database_backup_'.date('Y_m_d_H_i_s');
$total_path = $base_path . $filename;

$sql_user = 'xxxxx';
$sql_pass = 'xxxxx';

$data_save_req = 'mysqldump --all-databases' . ' --user=' . $sql_user .' --password=' . $sql_pass  . ' > "'. $total_path . '.sql' . '"';
$zip_data_req = 'zip ' . $total_path . '.zip ' . $total_path . '.sql';

exec($data_save_req);
exec($zip_data_req);

$db = new PDO('mysql:host='.$_CONFIG['sql_host'].';dbname='.$_CONFIG['sql_db'].';charset=utf8',$_CONFIG['sql_user'],$_CONFIG['sql_pass'],array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ));

$integrity = $db->query('SELECT  usr.usr_id, IFNULL(pur.pur_price, 0)+IFNULL(rec.rec_credit, 0)+IFNULL(virFrom.virFrom_amount, 0)+IFNULL(virTo.virTo_amount, 0) - usr_credit balanceMoinsUserCredit FROM `ts_user_usr` usr
 LEFT JOIN (
     SELECT -1*SUM(p.pur_price) AS pur_price, t.usr_id_buyer AS usr_id FROM t_purchase_pur p
         LEFT JOIN t_transaction_tra t ON t.tra_id = p.tra_id
     WHERE pur_removed = 0 AND tra_status = "V"
     GROUP BY t.usr_id_buyer
 ) AS pur ON pur.usr_id = usr.usr_id
 LEFT JOIN (
     SELECT SUM(r.rec_credit) AS rec_credit, r.usr_id_buyer AS usr_id FROM t_recharge_rec r
     GROUP BY r.usr_id_buyer
 ) AS rec ON rec.usr_id = usr.usr_id
 LEFT JOIN (
     SELECT -1*SUM(v.vir_amount) AS virFrom_amount, v.usr_id_from AS usr_id FROM t_virement_vir v
     GROUP BY v.usr_id_from
 ) AS virFrom ON virFrom.usr_id = usr.usr_id
 LEFT JOIN (
     SELECT SUM(v.vir_amount) AS virTo_amount, v.usr_id_to AS usr_id FROM t_virement_vir v
     GROUP BY v.usr_id_to
 ) AS virTo ON virTo.usr_id = usr.usr_id

WHERE (IFNULL(pur.pur_price, 0)+IFNULL(rec.rec_credit, 0)+IFNULL(virFrom.virFrom_amount, 0)+IFNULL(virTo.virTo_amount, 0) - usr_credit) != 0
ORDER BY balanceMoinsUserCredit');

$integrity = $integrity->fetchAll();

// foreach($integrity as $key => $data) {
//     echo $key .' => ';
//     var_export($data);
//     echo ',<br>';
// }

$dump = array();

if($dump!==$integrity) {
    $mail = new PHPMailer;

    $mail->isSMTP();                         // Set mailer to use SMTP
    $mail->Host = $_CONFIG['PHPMailer']['Host'];             // Specify main and backup SMTP servers
    $mail->SMTPAuth = $_CONFIG['PHPMailer']['SMTPAuth'];     // Enable SMTP authentication
    $mail->Username = $_CONFIG['PHPMailer']['Username'];     // SMTP username
    $mail->Password = $_CONFIG['PHPMailer']['Password'];     // SMTP password
    $mail->SMTPSecure = $_CONFIG['PHPMailer']['SMTPSecure']; // Enable TLS encryption, `ssl` also accepted
    $mail->Port = $_CONFIG['PHPMailer']['Port'];             // TCP port to connect to
    $mail->Encoding = 'base64';
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($_CONFIG['PHPMailer']['Username'], 'Contact PayIcam');
    $mail->addAddress($_CONFIG['PHPMailer']['integrity_mail']);
    $mail->isHTML(true);

    $mail->Subject = "Erreur d'intégrité";
    $mail->Body = "Il y a eu une erreur d'intégrité. Quelqu'un a potentiellement utilisé les codes de la base de données à mauvais escient.";

    $mail->send();

}