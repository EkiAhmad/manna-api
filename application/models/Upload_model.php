<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Upload_model extends CI_Model
{
    public $config = array();
    public $path = array(
        'product' => 'uploads/p/',
        'merchant' => 'uploads/m/',
        'store' => 'uploads/s/',
        'promo' => 'uploads/promo/',
        'default' => 'uploads/',
        'surprize' => 'uploads/surprize/',
        'payment' => 'uploads/payment/',
    );

    public function __construct()
    {
        parent::__construct();
        $this->config['upload_path'] = './' . $this->path['default'];
        $this->config['allowed_types'] = 'jpeg|jpg|png';
        $this->config['max_size'] = 512;
        $this->config['file_ext_tolower'] = true;
        $this->config['file_name'] = rand(999, 99999) . '-' . date('YmdHis');
    }

    public function product($sku)
    {
        $sku = str_replace('.', '-', $sku);
        $this->config['upload_path'] = './' . $this->path['product'];
        $this->config['file_name'] = date('YmdHis') . '-' . $sku;

        $this->load->library('upload', $this->config);

        if (!$this->upload->do_upload('file')) {
            return array('error' => true, 'result' => $this->upload->display_errors());
        } else {
            $resp = $this->upload->data();
            return array('error' => false, 'result' => $resp);
        }
    }

    public function surprize($name)
    {
        $name = str_replace('.', '-', $name);
        $this->config['upload_path'] = './' . $this->path['surprize'];
        $this->config['file_name'] = date('YmdHis') . '-' . $name;

        $this->load->library('upload', $this->config);

        if (!$this->upload->do_upload('file')) {
            return array('error' => true, 'result' => $this->upload->display_errors());
        } else {
            $resp = $this->upload->data();
            return array('error' => false, 'result' => $resp);
        }
    }
    
    public function payment($name)
    {
        $name = str_replace('.', '-', $name);
        $this->config['upload_path'] = './' . $this->path['payment'];
        $this->config['file_name'] = date('YmdHis') . '-' . $name;

        $this->load->library('upload', $this->config);

        if (!$this->upload->do_upload('file')) {
            return array('error' => true, 'result' => $this->upload->display_errors());
        } else {
            $resp = $this->upload->data();
            return array('error' => false, 'result' => $resp);
        }
    }

    public function promo($sku)
    {
        $sku = str_replace('.', '-', $sku);
        $this->config['upload_path'] = './' . $this->path['surprize'];
        $this->config['file_name'] = date('YmdHis') . '-' . $sku;

        $this->load->library('upload', $this->config);

        if (!$this->upload->do_upload('file')) {
            return array('error' => true, 'result' => $this->upload->display_errors());
        } else {
            $resp = $this->upload->data();
            return array('error' => false, 'result' => $resp);
        }
    }

    public function receipt($type, $str)
    {
        $this->config['upload_path'] = './' . $this->path[$type];
        $this->config['max_width'] = 210;
        $this->config['max_height'] = 120;
        $this->config['max_size'] = 212;
        $this->config['file_name'] = 'receipt-' . $this->slugify($str) . '-' . date('YmdHis');

        $this->load->library('upload', $this->config);

        if (!$this->upload->do_upload('file')) {
            return array('error' => true, 'result' => $this->upload->display_errors());
        } else {
            $resp = $this->upload->data();
            return array('error' => false, 'result' => $resp);
        }
    }

    public function storeImage($prefix)
    {
        $this->config['upload_path'] = './' . $this->path['store'];
        $this->config['max_size'] = 550;
        $this->config['file_name'] = $prefix . '-' . rand(100, 999) . '-' . date('YmdHis');

        $this->load->library('upload', $this->config);

        if (!$this->upload->do_upload('file')) {
            return array('error' => true, 'result' => $this->upload->display_errors());
        } else {
            $resp = $this->upload->data();
            return array('error' => false, 'result' => $resp);
        }
    }

    private function slugify($str)
    {
        $search = array('Ș', 'Ț', 'ş', 'ţ', 'Ş', 'Ţ', 'ș', 'ț', 'î', 'â', 'ă', 'Î', 'Â', 'Ă', 'ë', 'Ë');
        $replace = array('s', 't', 's', 't', 's', 't', 's', 't', 'i', 'a', 'a', 'i', 'a', 'a', 'e', 'E');
        $str = str_ireplace($search, $replace, strtolower(trim($str)));
        $str = preg_replace('/[^\w\d\-\ ]/', '', $str);
        $str = str_replace(' ', '-', $str);

        return preg_replace('/\-{2,}/', '-', $str);
    }
}
