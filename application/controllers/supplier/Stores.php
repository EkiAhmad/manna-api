<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlysupplier.php';

class Stores extends Onlysupplier
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Auth_model');
    }

    public function index_get($id = 0)
    {
        $sql = "SELECT `id`, `company`, `deleted` FROM `merchant`";
        $_merchants = $this->db->query($sql)->result_array();
        $merchants = array();
        foreach ($_merchants as $v) {
            $merchants[$v['id']] = array('company' => $v['company'], 'deleted' => $v['deleted']);
        }
        $_merchants = array(); // reset

        if ($this->supplierStores) {
            $sql = "SELECT A.`id`, IFNULL(A.`id_merchant`, '') AS `id_merchant`,
            '' AS `merchant_name`, A.`name` AS `company`, F.`label` AS `city`,
            D.`label` AS `business`, E.`label` AS `state`,
            C.`fullname` AS `pic`, IFNULL(C.`email`,'-') AS `pic_email`, C.`phone` AS `pic_phone`
            FROM `merchant_store` A
                JOIN `user_store` B ON A.`id` = B.`id_merchant_store`
                JOIN `user` C ON B.`id_user`=C.`id`
                JOIN `ref_business` D ON A.`business` = D.`value`
                JOIN `ref_state` E ON A.`state` = E.`value`
                JOIN `ref_city` F ON A.`city` = F.`id`
			WHERE A.`id` IN ? AND A.`deleted`=0 AND C.`deleted` = 0 AND C.`role`= ? ORDER BY 1 DESC";
            $data = $this->db->query($sql, array($this->supplierStores, $this->Auth_model->roles['store-admin']))->result_array();
            foreach ($data as $k => $v) {
                if ($v['id_merchant']) {
                    if (isset($merchants[$v['id_merchant']])) {
                        if ($merchants[$v['id_merchant']]['deleted'] == "1") {
                            unset($data[$k]);
                            continue;
                        } else {
                            $data[$k]['merchant_name'] = $merchants[$v['id_merchant']]['company'];
                        }
                    }
                }
            }
            $data = array_values($data);
        }

        $this->response(array('stores' => $data), REST_Controller::HTTP_OK);
    }
}
