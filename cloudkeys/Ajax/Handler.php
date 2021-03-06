<?php

class AjaxHandler extends BaseHttpHandler {

  public function get($params) {
    $this->response->header("Content-Type", "text/json;charset=utf-8");
    $userdata = $this->checkLogin();
    if($userdata !== null) {
      $data = json_decode($userdata->body, true);
      echo json_encode(array('version' => $data['metadata']['version'], 'data' => $data['data']));
    } else {
      echo json_encode(array('error' => true));
    }
  }

  public function post($params) {
    $this->response->header("Content-Type", "text/json;charset=utf-8");

    $userfile = $this->getUserfile();
    if($userfile === null) {
      echo json_encode(array('error' => true, 'type' => 'login'));
      return;
    }

    $userdata = $this->checkLogin();
    if($userdata === null) {
      echo json_encode(array('error' => true, 'type' => 'register'));
      return;
    }

    $data = json_decode($userdata->body, true);
    
    if($data['metadata']['version'] != $this->request->get('version')) {
      echo json_encode(array('error' => true, 'type' => 'version'));
      return;
    }

    if($this->request->get('checksum') != sha1($this->request->get('data'))) {
      echo json_encode(array('error' => true, 'type' => 'checksum'));
      return;
    }

    try {
      S3::copyObject($this->config->get('AWSS3Bucket'), $userfile, $this->config->get('AWSS3Bucket'), $userfile . '.' . idate('U') . '.backup');
      $data['metadata']['version'] = $this->request->get('checksum');
      $data['data'] = $this->request->get('data');
      S3::putObject(json_encode($data), $this->config->get('AWSS3Bucket'), $userfile);
    } catch(Exception $e) {
      echo json_encode(array('error' => true, 'message' => $e->getMessage()));
      return;
    }
    echo json_encode(array('version' => $data['metadata']['version'], 'data' => $data['data']));
  }

  private function getUserfile() {
    $userfile = $this->session->get('username');
    if($userfile == "") {
      return null;
    }
    return $userfile;
  }

  private function checkLogin() {
    $userfile = $this->getUserfile();
    if($userfile === null) {
      return null;
    }

    try {
      return S3::getObject($this->config->get('AWSS3Bucket'), $userfile);
    } catch(Exception $e) {
      return null;
    }
  }
}
