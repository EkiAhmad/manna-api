<?php
header("Content-Type: text/plain");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Cache-Control: no-cache");
header("Pragma: no-cache");

defined('BASEPATH') or exit('No direct script access allowed');

//require_once APPPATH . 'libraries/REST_Controller.php';

class Cron extends REST_Controller
{

    private $lastID = 0;

    public function __construct()
    {
        parent::__construct();
    }

    public function wa_notif_store_get()
    {
        $_hh = date('H');
        $_mm = date('i');

        $value = 'wa-notif-store';
        $start = array(
            'value' => $value,
        );
        $this->db->insert('cron_logs', $start);
        $this->lastID = $this->db->insert_id();

        $sql = "SELECT * FROM `settings` WHERE `value` = 'wa-notif-store' AND active = 1 LIMIT 1";
        $res = $this->db->query($sql)->row();
        if (!$res) {
            $end = array(
                'end_time' => date('Y-m-d H:i:s'),
                'logs' => 'Config nof found',
            );
            $this->db->update('cron_logs', $end, array('id' => $this->lastID));
            exit;
        }

        $content = trim($res->content);
        if ($content) {
            $content = json_decode($content, true);
            if ($content && $content['sent_time'] && $content['messages']) {
                $hh = date('H', strtotime($content['sent_time']));
                $mm = date('i', strtotime($content['sent_time']));
                $mmm = date('i', strtotime($content['sent_time'] . " +1 MINUTE"));

                if ($_hh == $hh && ($_mm == $mm || $mmm == $_mm)) {
                    $_logs = $this->sentStoreWaNotif($content['messages']);
                    $end = array(
                        'end_time' => date('Y-m-d H:i:s'),
                        'logs' => $_logs,
                    );
                    $this->db->update('cron_logs', $end, array('id' => $this->lastID));
                    exit;
                }
            }
        }

        $end = array(
            'end_time' => date('Y-m-d H:i:s'),
            'logs' => 'Skip, Config doest match',
        );
        $this->db->update('cron_logs', $end, array('id' => $this->lastID));
        exit;
    }

    private function sentStoreWaNotif($msg)
    {
        $sql = "SELECT A.`phone`, C.`id`, C.`name`
            FROM `user` A, `user_store` B, `merchant_store` C
        WHERE
            A.`id` = B.`id_user` AND
            B.`id_merchant_store` = C.`id` AND
            A.`id_merchant` IS NOT NULL AND
            C.`app_mmenu` = 1 AND
            C.`deleted` = 0";
        $res = $this->db->query($sql)->result_array();

        $days = array('Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu');
        $months = array(
            'Jan',
            'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
        );
        $x = date('n-w-z-d-Y');
        $xx = explode('-', $x);
        $n = $xx[0];
        $w = $xx[1];
        $z = $xx[2];
        $d = $xx[3];
        $Y = $xx[4];
        $z = str_pad($z, 3, "0", STR_PAD_LEFT);
        $header = $z . '/' . $Y . '/POS' . "\n" . $days[$w] . ', ' . $d . ' ' . $months[$n - 1] . ' ' . $Y;

        $end_date = "";
        $H = date('G');
        if ($H > 8) {
            $end_date = date("Y-m-d");
        } else {
            $end_date = date("Y-m-d", strtotime("-1 DAY"));
            $msg = str_ireplace("hari ini", "Kemarin", $msg);
        }

        $start_month = date('Y-m-01');
        $start_year = date('Y-01-01');

        $cnt = 1;
        $success = $error = 0;

        echo "### START: " . date('Y-m-d H:i:s') . " ###\n";
        foreach ($res as $v) {

            $_phone = trim($v['phone']);
            if ($_phone) {
                $_phone = preg_replace('/\D/', '', $_phone);
                if (strlen($_phone) > 5) {
                    $_two = substr($_phone, 0, 2);
                    $_three = substr($_phone, 0, 3);
                    if ($_two == "62") {
                        $_phone = "0" . substr($_phone, 2);
                    } else if ($_three == "062") {
                        $_phone = "0" . substr($_phone, 3);
                    }
                    $_check = substr($_phone, 0, 1);
                    if ($_check != "0") {
                        $_phone = "";
                    }
                }
            }
            $v['phone'] = $_phone;

            if ($v['phone'] && strlen($v['phone']) > 5) {
                //today
                $sql = "SELECT IFNULL(SUM(`total_price_sales`),0) as `total` FROM `sales` WHERE `date` = ? AND `id_merchant_store` = ? AND `status` = 1";
                $omzet_today = $this->db->query($sql, array($end_date, $v['id']))->row();
                $omzet_today = number_format($omzet_today->total, 0, '', '.');

                //this month
                $sql = "SELECT IFNULL(SUM(`total_price_sales`),0) as `total` FROM `sales` WHERE `date` BETWEEN ? AND ? AND `id_merchant_store` = ? AND `status` = 1";
                $omzet_month = $this->db->query($sql, array($start_month, $end_date, $v['id']))->row();
                $omzet_month = number_format($omzet_month->total, 0, '', '.');

                //this year
                $sql = "SELECT IFNULL(SUM(`total_price_sales`),0) as `total` FROM `sales` WHERE `date` BETWEEN ? AND ? AND `id_merchant_store` = ? AND `status` = 1";
                $omzet_year = $this->db->query($sql, array($start_year, $end_date, $v['id']))->row();
                $omzet_year = number_format($omzet_year->total, 0, '', '.');

                $_msg = str_replace(
                    array('[store_name]', '[omzet_today]', '[omzet_this_month]', '[omzet_this_year]'),
                    array($v['name'], $omzet_today, $omzet_month, $omzet_year),
                    $msg
                );

                $wa_msg = str_pad($cnt, 3, "0", STR_PAD_LEFT) . '/' . $header . "\n\n" . $_msg;

                if ($omzet_today || $omzet_month || $omzet_year) {
                    echo $v['phone'] . "\n";
                    echo $wa_msg;
                    if (false !== $this->sendWA($v['phone'], $wa_msg)) {
                        echo "\n--------------------- [SUCCESS]\n\n";
                        $success++;
                    } else {
                        echo "\n--------------------- [FAILED]\n\n";
                        $error++;
                    }
                } else {
                    $error++;
                }
            } else {
                $error++;
            }
            $cnt++;
        }
        echo "### END: " . date('Y-m-d H:i:s') . " ###\n\n\n";

        return "Total: " . ($cnt - 1) . ", Sent: $success, Skip: $error";
    }

    private function sendWA($phone, $messages)
    {
        $sent = false;

        $url = 'http://103.28.52.57:9040/api/service/sendwa';
        $fields = array(
            'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZCI6IjEifQ.IsWkez9DuDRnxzSsgkW418QgdP6vr9Z3qvIHTfaK5Ss',
            'phonenumber' => $phone,
            'message' => $messages,
        );

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, count($fields));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $result = curl_exec($ch);
            curl_close($ch);

            if ($result) {
                $result = json_decode($result, true);
                if (isset($result['sent']) && $result['sent'] == "succes") {
                    $sent = true;
                }
            }
        } catch (Exception $e) {

        }

        return $sent;
    }
}
