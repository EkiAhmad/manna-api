<?php
defined('BASEPATH') OR exit('No direct script access allowed');

//require_once APPPATH . 'libraries/REST_Controller.php';

class Test extends REST_Controller {

  public function __construct()
  {
    parent::__construct();
	}

	public function recovery_sales_get()
	{
		$sql = "SELECT `id` FROM `sales_bak` ORDER BY `id`";
		$sales = $this->db->query($sql)->result_array();
		foreach ($sales as $v) {
			$sql = "SELECT
			SUM(`price_product` * `quantity` ) AS `total_price_product`,
			SUM(`price_sales` * `quantity`) AS `total_price_sales`,
			SUM(`quantity`) AS `total_quantity`
			FROM `sales_product_bak`
			WHERE `id_sales` = ".$v['id']." GROUP BY `id_sales`";
			$products = $this->db->query($sql)->row();
			if ($products) {
				$data = array(
					'total_price_product' => $products->total_price_product,
					'total_price_sales' => $products->total_price_sales,
					'total_quantity' => $products->total_quantity
				);
				$this->db->update('sales_bak', $store, array('id' => $v['id']));
				echo $v['id'] - " Done";
			} else {
				echo $v['id'] - " Skip";
			}
			echo "<br/>";
		}
		echo "DONE";
	}

	public function wa_get()
  {
		$phone = $this->get('phone');
		if ($phone) {
			$msg = "Toko %s\nSales report - %s\nToday: Rp 78.000\nMTD: Rp 430.850\nYTD: Rp 4.578.150\n\n\nSalam,\nTim Manna POS.";

			$toko = "Sinar Bulan";
			$yesterday = date("d/m/Y", strtotime("-1 DAY"));

			$data = array(
					'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZCI6IjEifQ.IsWkez9DuDRnxzSsgkW418QgdP6vr9Z3qvIHTfaK5Ss',
					'phonenumber' => $phone,
					'message' => sprintf($msg, $toko, $yesterday)
			);
			$options = array(
					'http' => array(
							'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
							'method'  => 'POST',
							'content' => http_build_query($data)
					)
			);

			$url = 'http://103.28.52.57:9040/api/service/sendwa';
			$context = stream_context_create($options);

			try {
					$result = file_get_contents($url, false, $context);
					if (false !== $result = json_decode($result,true) ) {
							if ( isset($result['sent']) && $result['sent'] == "succes") {
									$this->response('Sales report sent successfully', REST_Controller::HTTP_OK);
							}
					}
					$this->response('Error while sending data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
			} catch(Exception $e) {
				$this->response('Error while sending data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
			}
		}
  }

  public function index_get()
  {
		$merchant = 1;
		$duplicate = $error = array();
		$total = 0;

		$rows = file(FCPATH . 'dua.csv');
		$last = 0;
		foreach ($rows as $k => $row) {
			if ($k < 2) {
				continue;
			}
			$last_category = 0;

			$datas = array_map('trim',explode(';',$row));

			if ($datas[1] == 'PPOB') {
				continue;
			}

			$category = ucwords(strtolower($datas[1]));
			$category_sub = ucwords(strtolower($datas[4]));

			if ($category) {
				$sql = "SELECT `id` FROM `category_product` WHERE `name` = ? LIMIT 1";
				$check = $this->db->query($sql, array($category))->row();
				if (!$check) {
					$_data = array();
					$_data['name'] = $category;
					$_data['createdBy'] = 1;
					if ($this->db->insert('category_product', $_data)) {
						$last = $last_category = $this->db->insert_id();
					}
				} else {
					$last = $last_category = $check->id;
				}
			} else {
				$last_category = $last;
			}

			if ($last_category && $category_sub) {
				$sql = "SELECT `id` FROM `category_product` WHERE `name` = ? LIMIT 1";
				$check = $this->db->query($sql, array($category_sub))->row();
				if (!$check) {
					$_data = array();
					$_data['id_parent'] = $last_category;
					$_data['name'] = $category_sub;
					$_data['createdBy'] = 1;
					if ($this->db->insert('category_product', $_data)) {
						$last_category = $this->db->insert_id();
					}
				} else {
					$last_category = $check->id;
				}
			}

			$sku = preg_replace('/\D/', '', $datas[2]);
			$produk = $datas[3];
			$hb = preg_replace('/\D/', '', $datas[9]);
			$hj = preg_replace('/\D/', '', $datas[10]);
			if ($last_category && $sku) {
				$sql = "SELECT `id` FROM `product` WHERE `id_merchant` = ? AND `sku` = ? LIMIT 1";
				$check = $this->db->query($sql, array($merchant, $sku))->row();
				if (!$check) {
					$_data = array(
						'id_merchant' => $merchant,
						'id_category_product' => $last_category,
						'sku' => $sku,
						'name' => $produk,
						'price' => $hj,
						'price_modal' => $hb,
						'createdBy' => 1
					);
					if ($this->db->insert('product', $_data)) {
						$total++;
					}
				} else {
					$duplicate[] = array(
						'id' => $check->id,
						'name' => $produk
					);
				}
			} else {
				$error[] = array_merge(array('line'=>($k+1)), $datas);
			}

		}
    $this->response(array(
			'total' => $total,
			'duplicate' => $duplicate,
			'error' => $error
		), REST_Controller::HTTP_OK);
	}

	public function batchimage_get()
	{
		$skus = array();
		if ($handle = opendir(FCPATH . 'product')) {
			while (false !== ($entry = readdir($handle))) {
					if ($entry != "." && $entry != "..") {
							$x = explode('.', $entry);
							$skus[] = $x[0];
					}
			}
			closedir($handle);
		}
		if ($skus) {
			$sql = "SELECT `id`, `sku` FROM `product` WHERE `sku` IN ? ORDER BY `id`";
			$data = $this->db->query($sql, array($skus))->result_array();
			foreach ($data as $r) {
				$fname = date('YmdHis') . '-' . $r['sku'] . '.png';

				$old = FCPATH . 'product/' . $r['sku'] . '.png';
				$new = FCPATH . 'uploads/p/' . $fname;
				if ( false === copy($old,$new)) {
					echo $r['id'] . ' - ERROR <br/>';
					break;
				} else {
					$sql = "UPDATE `product` SET `image` = ? WHERE `id` = ?";
					$this->db->query($sql,array($fname,$r['id']));
					echo $r['id'] . '<br/>';
				}
			}
		}
		echo "DONE";
	}

}
