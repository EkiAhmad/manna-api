<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlystore.php';

class Stores extends Onlystore
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Auth_model');
        $this->load->model('Upload_model');

    }

    public function summary_get()
    {
        $end_date = date('Y-m-d');
        $start_month = date('Y-m-01');
        $start_year = date('Y-01-01');
        $N = date('N', strtotime($end_date)) - 1;
        $start_week = date('Y-m-d', strtotime("$end_date -$N DAY"));

        $id_merchant_store = $this->storeID;

//today
        $sql = "SELECT IFNULL(SUM(`total_price_product`),0) AS `total_modal`,IFNULL(SUM(`total_price_sales`),0) AS `total` FROM `sales` WHERE `date` = ? AND `id_merchant_store` = ? AND `status` = 1";
        $_today = $this->db->query($sql, array($end_date, $id_merchant_store))->row();
        $today = intval($_today->total);
        $today_profit = $today - (intval($_today->total_modal));

//this week
        $sql = "SELECT IFNULL(SUM(`total_price_product`),0) AS `total_modal`,IFNULL(SUM(`total_price_sales`),0) AS `total` FROM `sales` WHERE `date` BETWEEN ? AND ? AND `id_merchant_store` = ? AND `status` = 1";
        $_this_week = $this->db->query($sql, array($start_week, $end_date, $id_merchant_store))->row();
        $this_week = intval($_this_week->total);
        $this_week_profit = $this_week - (intval($_this_week->total_modal));

//this month
        $sql = "SELECT IFNULL(SUM(`total_price_product`),0) AS `total_modal`,IFNULL(SUM(`total_price_sales`),0) AS `total` FROM `sales` WHERE `date` BETWEEN ? AND ? AND `id_merchant_store` = ? AND `status` = 1";
        $_this_month = $this->db->query($sql, array($start_month, $end_date, $id_merchant_store))->row();
        $this_month = intval($_this_month->total);
        $this_month_profit = $this_month - (intval($_this_month->total_modal));

//this year
        $sql = "SELECT IFNULL(SUM(`total_price_product`),0) AS `total_modal`,IFNULL(SUM(`total_price_sales`),0) AS `total` FROM `sales` WHERE `date` BETWEEN ? AND ? AND `id_merchant_store` = ? AND `status` = 1";
        $_this_year = $this->db->query($sql, array($start_year, $end_date, $id_merchant_store))->row();
        $this_year = intval($_this_year->total);
        $this_year_profit = $this_year - (intval($_this_year->total_modal));

        $this->response(
            array(
                'today' => intval($today),
                'this_week' => intval($this_week),
                'this_month' => intval($this_month),
                'this_year' => intval($this_year),
                'today_profit' => intval($today_profit),
                'this_week_profit' => intval($this_week_profit),
                'this_month_profit' => intval($this_month_profit),
                'this_year_profit' => intval($this_year_profit),
            ), REST_Controller::HTTP_OK);

    }
    public function summary_old_get()
    {
        $result = array(
            'store' => array(
                'total' => 0,
            ),
            'product' => array(
                'total' => 0,
            ),
            'sales' => array(
                'sales' => 0,
                'product_sales' => 0,
                'omzet' => 0,
            ),
        );

        $product = null;
        if ($this->merchantProductType == "1") {
            $sql = "SELECT COUNT(*) AS `total` FROM `product` WHERE `id_merchant` = ? AND `id_merchant_store` IS NULL AND `id_category_product` IS NOT NULL AND `deleted` = 0";
            $product = $this->db->query($sql, array($this->merchantID))->row();
        } else if ($this->merchantProductType == "2") {
            $sql = "SELECT COUNT(*) AS `total` FROM `product` WHERE `id_merchant` = ? AND `id_merchant_store` = ? AND `id_category_product` IS NOT NULL AND `deleted` = 0";
            $product = $this->db->query($sql, array($this->merchantID, $this->storeID))->row();
        } else {
            $sql = "SELECT COUNT(*) AS `total` FROM `product` WHERE `id_merchant` = ? AND `id_category_product` IS NOT NULL AND `deleted` = 0";
            $product = $this->db->query($sql, array($this->merchantID))->row();
        }
        $result['product']['total'] = intval($product->total);

        $sql = "SELECT COUNT(*) AS `sales`, SUM(`total_price_sales`) AS `omzet`, SUM(`total_quantity`) AS `product_sales` FROM `sales` WHERE `id_merchant_store` = ? AND `status` = 1 AND `deleted` = 0";
        $sales = $this->db->query($sql, array($this->storeID))->row();
        $result['sales']['sales'] = intval($sales->sales);
        $result['sales']['product_sales'] = floatval($sales->product_sales);
        $result['sales']['omzet'] = intval($sales->omzet);

        $this->response($result, REST_Controller::HTTP_OK);
    }

    public function index_get()
    {
        $this->response("Selalu mencintaimu...", REST_Controller::HTTP_OK);
    }

    public function profile_get()
    {
        $data = array();
        $sql = "SELECT A.`id`, A.`phone`, A.`fullname`, C.`name`, C.`business`, C.`state`, C.`city`, C.`address`, IFNULL(C.`image`,'') AS `image`, IFNULL(C.`bg_store`,'') AS `bg_store`, IFNULL(C.`bg_store_menu`,'') AS `bg_store_menu`, IFNULL(C.`bg_store_promo`,'') AS `bg_store_promo`, IFNULL(C.`bg_store_promo_surprize`,'') AS `bg_store_promo_surprize`, IFNULL(C.`slug`,'') AS `slug`, IFNULL(C.`phone`,'') AS `phone_store`, IFNULL(C.`order_settings`,'') AS `order_settings` FROM `user` A,`user_store` B, `merchant_store` C WHERE
		A.`id` = B.`id_user` AND B.`id_merchant_store` = C.`id` AND
		A.`id` = ? AND A.`id_merchant` = ? AND B.`id_merchant_store` = ? AND A.`deleted` = 0 AND C.`deleted` = 0 LIMIT 1";
        $data = $this->db->query($sql, array($this->userID, $this->merchantID, $this->storeID))->row_array();
        if ($data) {
            $storePath = base_url() . $this->Upload_model->path['store'];
            if ($data['image']) {
                $data['image'] = $storePath . $data['image'];
            }
            if ($data['bg_store']) {
                $data['bg_store'] = $storePath . $data['bg_store'];
            }
            if ($data['bg_store_menu']) {
                $data['bg_store_menu'] = $storePath . $data['bg_store_menu'];
            }
            if ($data['bg_store_promo']) {
                $data['bg_store_promo'] = $storePath . $data['bg_store_promo'];
            }
            if ($data['bg_store_promo_surprize']) {
                $data['bg_store_promo_surprize'] = $storePath . $data['bg_store_promo_surprize'];
            }
            if (!$data['order_settings']) {
                $data['order_settings'] = array(
                    'paymentMethods' => array('offline', 'online','point'),
                    'orderTypes' => array('dine_in', 'take_away'),
                    'orderQR' => "1",
                    'orderNotifDetailWA' => "0",
                );
            } else {
                $data['order_settings'] = json_decode($data['order_settings'], true);
            }
            $this->response($data, REST_Controller::HTTP_OK);
        } else {
            $this->response("Toko tidak ditemukan", REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    public function profile_post()
    {
        $sql = "SELECT `id`, IFNULL(`slug`,'') AS `slug`, IFNULL(`image`,'') AS `image`, IFNULL(`bg_store`,'') AS `bg_store`, IFNULL(`bg_store_menu`,'') AS `bg_store_menu`, IFNULL(`bg_store_menu`,'') AS `bg_store_menu` FROM `merchant_store` WHERE `id` = ? AND `app_mmenu` = 1 AND `deleted` = 0";
        $ori = $this->db->query($sql, array($this->storeID))->row();
        if (!$ori) {
            $this->response('Toko tidak ditemukan', REST_Controller::HTTP_BAD_REQUEST);
        }
        $sql = "SELECT `id`, `phone`, `password` FROM `user` WHERE `id` = ? AND `id_merchant` = ? AND `role` = ? AND `deleted` = 0 LIMIT 1";
        $oriUser = $this->db->query($sql, array($this->userID, $this->merchantID, $this->Auth_model->roles['store-admin']))->row();
        if (!$oriUser) {
            $this->response('User tidak ditemukan', REST_Controller::HTTP_BAD_REQUEST);
        }

        $cols = array(
            'slug',
            'name',
            'business',
            'address',
            'state',
            'city',
            'fullname',
            'phone_store',
            'phone',
            'password',
            'oldpassword',
            'order_settings',
        );

        if ($_FILES) {
            $data = json_decode($this->post('data'), true);
            if (!$data) {
                $this->response("Form harus diisi", REST_Controller::HTTP_BAD_REQUEST);
            }
            foreach ($cols as $col) {
                if ($col == 'order_settings') {
                    $$col = isset($data[$col]) ? $data[$col] : array();
                } else {
                    $$col = isset($data[$col]) ? trim($data[$col]) : '';
                }
            }
        } else {
            foreach ($cols as $col) {
                if ($col == 'order_settings') {
                    $$col = $this->post($col);
                } else {
                    $$col = trim($this->post($col));
                }
            }
        }

        if (!is_array($order_settings)) {
            $order_settings = array();
        }
        if (!$order_settings) {
            $this->response("Pengaturan pesanan harus diset", REST_Controller::HTTP_BAD_REQUEST);
        }

        if (!$phone_store) {
            $phone_store = null;
        }

        $phone = preg_replace('/\D/', '', $phone);
        if (!$phone) {
            $this->response('Nomor HP harus diisi', REST_Controller::HTTP_BAD_REQUEST);
        }
        if ($oriUser->phone != $phone) {
            $sql = "SELECT `id` FROM `user` WHERE `id` != ? AND `phone` = ? AND `deleted` = 0 LIMIT 1";
            $exist = $this->db->query($sql, array($this->userID, $phone))->row();
            if ($exist && $exist->id) {
                $this->response('Nomor HP sudah terdaftar', REST_Controller::HTTP_BAD_REQUEST);
            }
        }

        if (!$ori->slug) {
            if ($slug) {
                $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower($slug));
                $slug = preg_replace('/-+/', '-', $slug);
                $slug = trim($slug, '-');
            }
            if (!$slug) {
                $this->response('URL Alias salah', REST_Controller::HTTP_BAD_REQUEST);
            }
            $sql = "SELECT `id` FROM `merchant_store` WHERE `id` != ? AND `slug` = ? AND `deleted` = 0";
            $exist = $this->db->query($sql, array($this->userID, $slug))->row();
            if ($exist && $exist->id) {
                $this->response('URL Alias sudah terdaftar', REST_Controller::HTTP_BAD_REQUEST);
            }
        }

        if ($city) {
            $city = intval($city);
            $sql = "SELECT `state` FROM `ref_city` WHERE `id` = ?";
            $getState = $this->db->query($sql, array($city))->row_array();
            if ($getState) {
                $state = $getState['state'];
            }
        }

        if ($oldpassword) {
            if (password_verify($oldpassword, $oriUser->password)) {
                if ($password) {
                    $password = $this->Auth_model->storeHashPassword($password);
                    if (!$password) {
                        $this->response('Gagal memproses data', REST_Controller::HTTP_BAD_REQUEST);
                    }
                }
            } else {
                $this->response('Password lama salah', REST_Controller::HTTP_BAD_REQUEST);
            }
        }

        $this->db->trans_begin();
        try {
            $stores = array();
            foreach (array(
                'slug',
                'name',
                'business',
                'state',
                'address',
                'city',
            ) as $col) {
                if ($col == 'slug') {
                    if ($ori->slug) {
                        continue;
                    }
                }
                $stores[$col] = $$col;
            }
            $stores["phone"] = $phone_store;
            $stores["updatedBy"] = $this->userID;
            $stores["order_settings"] = json_encode($order_settings);

            $user = array();
            $role = $this->Auth_model->roles['store-admin'];
            foreach (array(
                'fullname',
                'phone',
                'password',
            ) as $col) {
                if ($col == 'password') {
                    if ($password) {
                        $user[$col] = $$col;
                    }
                } else {
                    $user[$col] = $$col;
                }
            }
            $user["updatedBy"] = $this->userID;
            $this->db->update('user', $user, array('id' => $oriUser->id));

            if ($_FILES) {
                $upload = $this->Upload_model->receipt('store', $name);
                if ($upload['error']) {
                    $this->db->trans_rollback();
                    $errMsg = str_replace(array('<p>', '</p>'), '', $upload['result']);
                    $this->response($errMsg, REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                } else {
                    $stores['image'] = $upload['result']['file_name'];
                    if ($ori && $ori->image) {
                        @unlink(FCPATH . $this->Upload_model->path['store'] . $ori->image);
                    }
                }
            }
            $this->db->update('merchant_store', $stores, array('id' => $this->storeID));

            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                $this->response('Database error', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            } else {
                $this->db->trans_commit();
                $this->response($this->storeID, REST_Controller::HTTP_OK);
            }

        } catch (Exception $e) {
            $this->db->trans_rollback();
            $this->response('Gagal memproses data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function upload_post()
    {
        $sql = "SELECT `id`, IFNULL(`image`,'') AS `image`, IFNULL(`bg_store`,'') AS `bg_store`, IFNULL(`bg_store_menu`,'') AS `bg_store_menu`, IFNULL(`bg_store_promo`,'') AS `bg_store_promo`, IFNULL(`bg_store_promo_surprize`,'') AS `bg_store_promo_surprize` FROM `merchant_store` WHERE `id` = ? AND `app_mmenu` = 1 AND `deleted` = 0";
        $ori = $this->db->query($sql, array($this->storeID))->row_array();
        if (!$ori) {
            $this->response('Toko tidak ditemukan', REST_Controller::HTTP_BAD_REQUEST);
        }
        $data = $this->post('data');
        $data = trim($data);

        if ($_FILES) {
            if ($data && isset($ori[$data])) {
                $upload = $this->Upload_model->storeImage($data . '-' . $ori['id']);
                if ($upload['error']) {
                    $errMsg = str_replace(array('<p>', '</p>'), '', $upload['result']);
                    $this->response($errMsg, REST_Controller::HTTP_BAD_REQUEST);
                } else {
                    if ($ori[$data]) {
                        @unlink(FCPATH . $this->Upload_model->path['store'] . $ori[$data]);
                    }

                    $stores = array($data => $upload['result']['file_name']);
                    if ($this->db->update('merchant_store', $stores, array('id' => $this->storeID))) {
                        $storeURLPath = base_url() . $this->Upload_model->path['store'] . $upload['result']['file_name'];
                        $this->response($storeURLPath, REST_Controller::HTTP_OK);
                    }
                }
            }
        }

        $this->response('Gagal memproses data', REST_Controller::HTTP_BAD_REQUEST);
    }
}
