<?php

$base_path = "D:/Gregoire/ICAM/PayIcam/Backups/";
$filename = 'database_backup_'.date('Y_m_d_H_i_s');
$total_path = $base_path . $filename;

$sql_user = 'xxxxx';
$sql_pass = 'xxxxx';

$data_save_req = 'mysqldump --all-databases' . ' --user=' . $sql_user .' --password=' . $sql_pass  . ' > "'. $total_path . '.sql' . '"';
$zip_data_req = 'zip ' . $total_path . '.zip ' . $total_path . '.sql';

exec($data_save_req);
exec($zip_data_req)
