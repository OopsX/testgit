<?php if(!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 学员模型
 */
class Student_model extends MY_Model {
    
    public $table = 'students';
    public $status_and_time = true;

    public function add_student($data){
        $this->load->helper('uc');
        $area_id = !empty($data['area_district_id']) ? $data['area_district_id'] : 
                    ( !empty($data['area_city_id']) ? $data['area_city_id'] : 
                    ( !empty($data['area_province_id']) ? $data['area_province_id'] : 0 ) );

        $user_goods = isset($data['good_ids']) ? $data['good_ids'] : false;
        $data['password'] = trim($data['password']);
        $password = empty( $data['password'] ) ? '111111' : $data['password'];
        if($data['email']){
            $email = $data['email'];
        }else{
            $email = $data['mobile'].'@139.com';
        }
        $this->find_status = false;
        // $has_user = $this->get_one_by(array('email'=>$data['email']));
        $isDelete = $this->get_one_by(array('mobile'=>$data['mobile']));
        // if($has_user && $isDelete && $has_user->uc_id != $isDelete->uc_id)return array('uc_id'=>-1,'msg'=>'该邮箱和'.$has_user->name.'重复，请核对');
        if($isDelete && $isDelete->status == 0 && $isDelete->uc_id){
            //已删除,更新
            $uc_id = $isDelete->uc_id;
            $isInsert = false;
        }else{
            if($user =  uc_get_user($data['mobile'])){
                $uc_id = $user[0];
            }else{
                $uc_id = uc_user_register($data['mobile'], $password, $email);//var_dump($uc_id);die;
                if( !$uc_id || $uc_id < 0) {
                    if($uc_id == -1) {
                        return array('uc_id'=>$uc_id,'msg'=>'用户名不合法');
                    } elseif($uc_id == -2) {
                        return array('uc_id'=>$uc_id,'msg'=>'包含要允许注册的词语');
                    } elseif($uc_id == -3) {
                        return array('uc_id'=>$uc_id,'msg'=>'用户名已经存在');
                    } elseif($uc_id == -4) {
                        return array('uc_id'=>$uc_id,'msg'=>'该邮箱格式有误');
                    } elseif($uc_id == -5) {
                        return array('uc_id'=>$uc_id,'msg'=>'该邮箱不允许注册');
                    } elseif($uc_id == -6) {
                        return array('uc_id'=>$uc_id,'msg'=>'该邮箱已经存在');
                    } else {
                        return array('uc_id'=>$uc_id,'msg'=>'未定义');
                    }
                }
            }
            $isInsert = true;
        }
        //$this->find_status = 99;

        $student_data = array(
            'uc_id' => $uc_id,
            'name' => $data['name'],
            'area_id' => $area_id,
            'school_id' => $data['school_id']+0,
            'group_id' => $data['group_id'],
            'mobile' => $data['mobile']+0,
            'qq' => $data['qq']+0,
            'student_from' => 3,
            'status'=>99,
            'admin_id' => $this->admin_id,
            'email' => $email
        );
        if($isInsert){
            $id = $this->insert($student_data);
        }else{
            $id = $this->update($isDelete->id,$student_data);
        }
        //var_dump($user);die;
        if( !$id ) return array('uc_id'=>$uc_id,'msg'=>'添加用户失败');
        if($isInsert){
            if ( $user_goods ) $this->set_student_goods($uc_id, $user_goods);
        }else{
            if ( $user_goods ) $this->set_student_goods($uc_id, $user_goods,true);
        }

        $this->load->model('log_model');
        $log_data = $student_data;
        $log_data['good_ids'] = $user_goods;
        $this->log_model->add_log($this->admin_id, $uc_id, 'add_student' ,$log_data);

        return array('uc_id'=>$uc_id,'msg'=>'添加用户成功');
    }

    public function edit_student($id, $data){
        $this->load->helper('uc');
        $area_id = !empty($data['area_district_id']) ? $data['area_district_id'] : 
                    ( !empty($data['area_city_id']) ? $data['area_city_id'] : 
                    ( !empty($data['area_province_id']) ? $data['area_province_id'] : 0 ) );

        $user_goods = isset($data['good_ids']) ? $data['good_ids'] : false;
        $user_zhenti_goods = isset($data['zhenti_good_ids']) ? $data['zhenti_good_ids'] : false;
        $student_data = array(
            'name' => $data['name'],
            'area_id' => $area_id,
            'school_id' => $data['school_id']+0,
            'group_id' => $data['group_id'],
            'mobile' => $data['mobile']+0,
            'qq' => $data['qq']+0,
            'email' => $data['email'] ? $data['email'] : $data['mobile'].'@139.com'
        );

        $email = $student_data['email'];
        $data['password'] = trim($data['password']);
        //$rs = uc_user_edit($data['mobile'], '', $data['password'], $email, 1);
        $rs = uc_user_edit_mobile_password_email($id, $data['mobile'], $data['password'], $email);
        // if( $rs<0 ) return false;

        $this->load->model('log_model');
        $log_data = $student_data;
        if($user_zhenti_goods){
            $new_good_ids = array_merge($user_goods,explode(',', $user_zhenti_goods));  
        }else{
            $new_good_ids = $user_goods;
        }
        $log_data['good_ids'] = $new_good_ids;
        $this->log_model->add_log($this->admin_id, $id, 'edit_student' ,$log_data);
        
        $rs = $this->update_where(array('uc_id'=>$id), $student_data);
        if( !$rs ) return false;
        if ( $user_goods ) $this->set_student_goods($id, $new_good_ids, true);

        return true;
    }

    public function parse_students_excel($file,$issu=false){
        $this->load->file(APPPATH.'libraries/PHPExcel/IOFactory.php');
        if($file->type=="xlsx"){
            $reader = PHPExcel_IOFactory::createReader('Excel2007');
        }else{
            $reader = PHPExcel_IOFactory::createReader('Excel5');
        }
        $reader->setLoadAllSheets();
        $reader->setReadDataOnly(true);
        $objExcel = $reader->load($file->fullPath);
        $sheet_count = $objExcel->getSheetCount();
        $students = array();
        $emailArr = array();
        $mobileArr = array();
        for($index = 0; $index < $sheet_count; $index++){
            $sheet = $objExcel->getSheet($index);
            if( !$sheet ) continue;
            
            $sheet_row_count = $sheet->getHighestRow();
            for($row_index = 2; $row_index <= $sheet_row_count; $row_index++){
                $password = trim( $sheet->getCell('D'.$row_index)->getValue() );
                $group_name = trim( $sheet->getCell('E'.$row_index)->getValue() );
                $this->load->model('group_model');
                $this->group_model->db->where_not_in('id',array(7, 8, 9));
                $rs = $this->group_model->get_one_by(array('name'=>$group_name, 'type'=>1));
                if ( !$rs ){
                    $this->parse_error = sprintf('第 %d 行角色填写错误', $row_index);
                    return false;
                }
                $group_id = $rs->id;

                $area_name = trim( $sheet->getCell('F'.$row_index)->getValue() );
                $this->load->model('areas_model');
                $rs = $this->areas_model->get_one_by(array('name'=>$area_name,'type'=>2));
                if ( !$rs ){
                    $this->parse_error = sprintf('第 %d 行地区填写错误', $row_index);
                    return false;
                }
                $area_id = $rs->id;

                $school_name = trim( $sheet->getCell('G'.$row_index)->getValue() );
                $this->load->model('school_model');
                $rs = $this->school_model->get_one_by(array('name'=>$school_name,'area_id'=>$area_id));
                if ( !$rs ){
                    $this->parse_error = sprintf('第 %d 行学校填写错误', $row_index);
                    return false;
                }
                $school_id = $rs->id;

                $pay_type = trim( $sheet->getCell('H'.$row_index)->getValue() );
                if ( !$pay_type ){
                    $this->parse_error = sprintf('第 %d 行所报班次填写错误', $row_index);
                    return false;
                }
                $pay_type = explode('|', $pay_type);
                $this->load->model('goods_model');
                $good_ids = array();
                foreach ($pay_type as $v) {
                    // if(!$issu && $v == '真题班'){ //如果不是超级管理员,又添加了真题班的学员
                    //     $this->parse_error = sprintf('第 %d 行所报班次您无权限添加,请联系管理员', $row_index);
                    //     return false;
                    // }
                    $good = $this->goods_model->get_goods_by_name($v);
                    if(!$issu && $good){
                        if($good->type == 1 || in_array($good->id, array('48','83','86','99','100','101','102'))){
                            $this->parse_error = sprintf('第 %d 行所报班次您无权限添加,请联系管理员', $row_index);
                            return false;
                        }
                    }

                    // $good_id = $this->goods_model->get_good_id_by_name($v);
                    if ( !$good->id ){
                        $this->parse_error = sprintf('第 %d 行所报班次不存在', $row_index);
                        return false;
                    }
                    $good_ids[] = $good->id;
                }

                $email = trim( $sheet->getCell('B'.$row_index)->getValue() );
                $mobile = trim( $sheet->getCell('C'.$row_index)->getValue() );
                if(!$mobile){
                    $this->parse_error = sprintf('第 %d 行未填写的手机,请核对', $row_index);
                    return false;
                }else{
                    if(!valid_phone_number($mobile)){
                        $this->parse_error = sprintf('第 %d 行手机号码格式错误,请核对', $row_index);
                        return false;
                    }
                }
                if(!$email){
                    $this->parse_error = sprintf('第 %d 行未填写的邮箱,请核对', $row_index);
                    return false;
                }else{
                    if(!preg_match("/^[0-9a-zA-Z]+@(([0-9a-zA-Z]+)[.])+[a-z]{2,4}$/i",$email)){
                        $this->parse_error = sprintf('第 %d 行邮箱格式错误,请核对', $row_index);
                        return false;
                    }
                }
                //$isWhere = array("email ='".$email."' ");
                //$isUser = $this->get_by(implode(' OR ', $isWhere));
                //$this->find_status = false;
                // $isEmailUser = $this->get_one_by(array("email"=>$email));
                
                // if($isEmailUser){
                //     //$this->_render_ajax(array('errno' => 1, 'err' => '当前用户邮箱或手机已经存在,请核对后重新输入'));
                //     // $this->parse_error = sprintf('第 %d 行填写的手机或邮箱已经存在,请核对', $row_index);
                //     if(count($isEmailUser) == 1){
                //         if($mobile != $isEmailUser->mobile){
                //             $this->parse_error = sprintf('第 %d 行填写的邮箱和'. $isEmailUser->name .'的邮箱重复,请核对', $row_index);
                //             return false;
                //         }
                //     }else{
                //         $this->parse_error = sprintf('第 %d 行填写的邮箱已经存在,请核对', $row_index);
                //     }
                //     //$this->parse_error = sprintf('第 %d 行填写的邮箱已经存在,请核对', $row_index);
                // }
                //$this->find_status = false;
                // $isMobileUser = $this->get_one_by(array("mobile"=>$mobile));
                // if($isMobileUser){
                //     //$this->_render_ajax(array('errno' => 1, 'err' => '当前用户邮箱或手机已经存在,请核对后重新输入'));
                //     // $this->parse_error = sprintf('第 %d 行填写的手机或邮箱已经存在,请核对', $row_index);
                //     if(count($isMobileUser) == 1){
                //         if($email != $isMobileUser->email){
                //             $this->parse_error = sprintf('第 %d 行填写的手机号和'. $isMobileUser->name .'的手机号重复,请核对', $row_index);
                //             return false;
                //         }
                //     }else{
                //         $this->parse_error = sprintf('第 %d 行填写的手机号已经存在,请核对', $row_index);
                //     }
                //     //$this->parse_error = sprintf('第 %d 行填写的邮箱已经存在,请核对', $row_index);
                // }
                // if(!preg_match("/^[0-9a-zA-Z]+@(([0-9a-zA-Z]+)[.])+[a-z]{2,4}$/i",$email) && $email){
                //      $this->parse_error = sprintf('第 %d 行邮箱格式错误,请核对', $row_index);
                //      return false;
                // }
                
                
                if(in_array($email, $emailArr)){
                     $this->parse_error = sprintf('第 %d 行填写的邮箱在本表中有重复,请核对', $row_index);
                    return false;
                }
                if(in_array($mobile, $mobileArr)){
                     $this->parse_error = sprintf('第 %d 行填写的手机在本表中有重复,请核对', $row_index);
                    return false;
                }
                $emailArr[] = $email;
                $mobileArr[] = $mobile;

                $students[] = array(
                    'name' => trim( $sheet->getCell('A'.$row_index)->getValue() ),
                    'email' => trim( $sheet->getCell('B'.$row_index)->getValue() ),
                    'mobile' => trim( $sheet->getCell('C'.$row_index)->getValue() ),
                    'password' => $password ? $password : '111111',
                    'group_id' => $group_id,
                    'school_id' => $school_id,
                    'area_id' => $area_id,
                    'qq' => trim( $sheet->getCell('I'.$row_index)->getValue() ),
                    'good_ids' => $good_ids,
                    'admin_id' => $this->admin_id
                );
            }
        }

        if ( count($students)>500 ){
            $this->parse_error = '每次导入用户数量最多为500';
            return false;
        }

        return $students;
    }

    public function get_parse_excel_error(){
        return $this->parse_error;
    }

    public function add_students_bash($students,$check_student,$su){
        $this->load->helper('uc');
        $this->load->model('log_model');
        //print_r($students);exit;
        $success = 0;
        foreach ($students as $k => $v) {
            if ( isset($v['mobile']) ) $username = $v['mobile'];
            else $username = $v['email'];

            //$isWhere = array(" `mobile` ='".$v['mobile']."' ");
            $this->find_status = false;
            $isUser = $this->get_one_by(array('mobile'=>$v['mobile']));
            //$isUser = $this->query("mobile ='".$v['mobile']."'");
            //var_dump($isUser);//exit;
            // $this->find_status = 99;
            if(!$v['email']) {
                $email = $username.'@139.com';
            }else{
                $email = $v['email'];
            }
            $v['password'] = trim($v['password']);
            if($isUser){//用户已经存在即更新
                if($v['password']){
                    $this->load->model('user_model');
                    $this->user_model->change_password_admin($isUser->uc_id, $v['password']);
                }
                $student_data = array(
                    'name' => $v['name'],
                    'email' => $email,
                    'mobile' => $v['mobile'],
                    'school_id' => $v['school_id'],
                    'area_id' => $v['area_id'],
                    'group_id' => $v['group_id'],
                    'student_from' => 2,
                    'status' => 99
                );
                $this->update($isUser->id,$student_data);
                if ( $v['good_ids'] ){
                    $this->load->model('goods_model');
                    if($check_student == 1){
                        $this->set_student_goods_when_user_exist($isUser->uc_id, $v['good_ids'],true,$su);
                    }else{
                        $this->set_student_goods_when_user_exist($isUser->uc_id, $v['good_ids'],'',$su);
                    }
                }
                $student_data['good_ids'] = $v['good_ids'];
                $this->log_model->add_log($this->admin_id, $isUser->uc_id, 'add_student' ,$student_data);
                $success++;
            }else{
                if($user =  uc_get_user($username)){
                    $uc_id = $user[0];
                }else{
                    $uc_id = uc_user_register($username, $v['password'], $email);//var_dump($uc_id);die;
                    if ( $uc_id <= 0 ) continue;
                }

                $student_data = array(
                    'name' => $v['name'],
                    'email' => $email,
                    'mobile' => $v['mobile'],
                    'school_id' => $v['school_id'],
                    'area_id' => $v['area_id'],
                    'group_id' => $v['group_id'],
                    'uc_id' => $uc_id,
                    'student_from' => 2,
                    'admin_id' => $this->admin_id
                );
                if ( $v['good_ids'] ){
                    $this->load->model('goods_model');
                    $this->set_student_goods($uc_id, $v['good_ids']);
                }
                
                $id = $this->insert($student_data);
                if( !$id ) continue;

                $student_data['good_ids'] = $v['good_ids'];
                $this->log_model->add_log($this->admin_id, $uc_id, 'add_student' ,$student_data);
                $success++;
            }  
        }
        return $success;
    }

    public function uc_reg_students($students){
        $this->load->helper('uc');
        foreach ($students as $k => &$v) {
            if ( isset($v['mobile']) ) $username = $v['mobile'];
            else $username = $v['email'];
            $uc_id = uc_user_register($username, $v['password'], $v['email']);
            if ( !$uc_id ) {
                unset($students[$k]);
                continue;
            };
            $v['uc_id'] = $uc_id;
        }
        return $students;
    }

    public function parse_students_insert_data($students){
        $data = array();
        foreach ($students as $k => $v) {
            $data[] = array(
                'name' => $v['name'],
                'email' => $v['email'],
                'mobile' => $v['mobile'],
                'school_id' => $v['school_id'],
                'area_id' => $v['area_id'],
                'group_id' => $v['group_id'],
                'pay_type' => $v['pay_type'],
                'uc_id' => isset($v['uc_id']) ? $v['uc_id'] : null,
            );
        }
        return $data;
    }

    public function set_student_goods($student_id, $good_ids, $is_del=false){
        if( $is_del ) $this->db->delete('student_goods', array('student_id'=>$student_id));
        $data = array();
        if( is_array($good_ids) ){
            foreach ($good_ids as $k => $v) {
                $data[] = array(
                    'student_id' => $student_id,
                    'good_id' => $v,
                    'add_time' => time(),
                );
            }
        }else{
            $data[] = array(
                'student_id' => $student_id,
                'good_id' => $good_ids,
                'add_time' => time(),
            );
        }
        return $this->db->insert_batch('student_goods', $data);
    }

    public function set_student_goods_when_user_exist($student_id, $good_ids, $is_del=false,$is_administortar=false){
        if( $is_del ){
            if($is_administortar){
                $this->db->delete('student_goods', array('student_id'=>$student_id));
            }else{
                $stu_goods = $this->get_student_goods($student_id);
                if($stu_goods){
                    $this->load->model('goods_model');
                    foreach ($stu_goods as $key => $val) {
                        $good = $this->goods_model->get_one($val);
                        if($good && $good->type == 0){
                            $this->db->delete('student_goods', array('student_id'=>$student_id,'good_id'=>$val));
                        }
                    }
                }
            }
        }
        
        $data = array();
        if( is_array($good_ids) ){
            foreach ($good_ids as $k => $v) {
                $data[] = array(
                    'student_id' => $student_id,
                    'good_id' => $v,
                    'add_time' => time(),
                );
            }
        }else{
            $data[] = array(
                'student_id' => $student_id,
                'good_id' => $good_ids,
                'add_time' => time(),
            );
        }
        return $this->db->insert_batch('student_goods', $data);
    }

    public function get_student_goods($student_id){
        $this->db->where('student_id', $student_id);
        $rs = $this->db->get('student_goods')->result();
        if ( !$rs ) return false;

        $good_ids = array();
        foreach ($rs as $k => $v) {
            $good_ids[] = $v->good_id;
        }
        return $good_ids;
    }

    public function get_by_ids($arr,$ids){
        if(is_array($ids)){
            $this->db->where_in('id', $ids);
        }else{
            $this->db->where('id', $ids);
        }
        //$this->db->select('id,name');
        $res = $this->get_by($arr);
        return $res;
    }

    public function get_by_ucids($arr,$ids){
        if(is_array($ids)){
            $this->db->where_in('uc_id', $ids);
        }else{
            $this->db->where('uc_id', $ids);
        }
        //$this->db->select('id,name');
        $res = $this->get_by($arr,'uc_id asc');
        return $res;
    }

    public function get_block_by_uc_ids($uc_ids,$grade_id){
        $this->db->select('id, uc_id, name, email, mobile');
        $rs = $this->get_by('`uc_id` IN ('.$uc_ids.')', '', '', '', false);
        if (!$rs) return false;
        $this->load->model('grade_relation_model');
        $stu_grade = $this->grade_relation_model->get_by(array('model'=>'student','grade_id'=>$grade_id));
        $add_grade_info = array();
        foreach ($stu_grade as $key => $val) {
            if($val->created)$add_grade_info[$val->item_id] = date("Y-m-d H:i:s",$val->created);
        }
        $this->load->helper('text');
        foreach ( $rs as $v ){
            $v->email = hide_email_and_phone($v->email);    
            // $v->mobile = hide_email_and_phone($v->mobile);
            $v->mobile = $v->mobile;
            $v->add_time = $add_grade_info[$v->uc_id];
        }
        return $rs;
    }
    
    public function get_by_uc_ids($uc_ids){
        $this->db->select('id, uc_id, name, email, mobile');
        $rs = $this->get_by('`uc_id` IN ('.$uc_ids.')', '', '', '', false);
          if (!$rs) return false; 
          foreach ( $rs as $v ){
              $res[$v->uc_id] = $v;
          }
          return $res;
    }

    public function get_student_info_by_uc_ids($uc_ids){
        $this->db->select('id, uc_id, name, email, mobile,area_id');
        $rs = $this->get_by('`uc_id` IN ('.$uc_ids.')', '', '', '', false);
          if (!$rs) return false; 
          $this->load->model('areas_model');
          foreach ( $rs as $v ){
            $area = $this->areas_model->get_one($v->area_id);
            $v->area_name = $area->name;
            $res[$v->uc_id] = $v;
          }
          return $res;
    }

    public function get_student_by_uc_id($uc_id){
        $this->db->select('id, uc_id, name, email, mobile, group_id');
        $where = array('uc_id'=>$uc_id);
        $rs = $this->get_one_by($where);
        if( !$rs ) return false;
        
        $user = array(
            'uid' => $rs->id,
            'uc_id' => $rs->uc_id,
            'name' => $rs->name,
            'email' => $rs->email,
            'mobile' => $rs->mobile,
            'group_id' => $rs->group_id,
        );
        return $user;
    }

    public function get_student_by_token($token){
        $this->db->select('id, uc_id, name, email, mobile, group_id');
        $where = array('token' => $token);
        $rs = $this->get_one_by($where);
        if( !$rs ) return false;
        
        $user = array(
            'uid'      => $rs->id,
            'uc_id'    => $rs->uc_id,
            'name'     => $rs->name,
            'email'    => $rs->email,
            'mobile'   => $rs->mobile,
            'group_id' => $rs->group_id,
        );
        
        return $user;
    }

    public function get_users_by_goods($goods, $area_id=0, $q=''){
        if ( !$goods ) return false;
        $good_ids = array_keys($goods);
        
        $this->db->where_in('good_id', $good_ids);
        $rs = $this->db->get('student_goods')->result();
        if ( !$rs ) return false;

        $user_ids = array();
        $user_goods = array();
        foreach ($rs as $v) {
            $user_ids[] = $v->student_id;
            $user_goods[$v->student_id][] = $goods[$v->good_id];
        }

        $where = array();
        if($q) {
            if ( is_numeric($q) ) $where[] = "`mobile` LIKE '%$q%'";
            else  $where[] = "`name` LIKE '%$q%'";
        }
        //if($school_id) $where[] = "`school_id` = {$school_id}";
        if($area_id) $where[] = "`area_id` = {$area_id}";
        if($where) $where = implode(' and ', $where);

        $this->load->model('student_model');
        $this->db->where_in('uc_id', $user_ids);
        $this->db->order_by('id desc');
        $users = $this->student_model->get_by($where);
        foreach ($users as $k => $v) {
            $v->goods_info = $user_goods[$v->uc_id];
        }
        return $users;
    }

    public function get_uid_by_goods_id($good_ids){
        if(is_array($good_ids)){
            $this->db->where_in('good_id', $good_ids);
        }else{
            $this->db->where('good_id',$good_ids);
        }
        
        $rs = $this->db->get('student_goods')->result();
        $user_ids = array();
        foreach($rs as $key=>$val){
            $user_ids[] = $val->student_id;
        }
        return $user_ids;
    }
    
    //预付金商品处理
    public function blance_good($user_id,$blance)
    {   
        $data[] = array('user_id' => $user_id, 'blance' => $blance);
        return $this->db->insert_batch('user_blance', $data);
    }

    public function get_uid_by_name($name){
        $this->db->like('name', $name);
        
        $students = $this->get_by();
        $res = array();
        foreach($students as $key=>$val){
            $res[] = $val->uc_id;
        }
        return $res;
    }
    public function search_blance($mobile){
        $this->load->helper('uc');
        $user_info = uc_get_user($mobile);
        $this->db->where('user_id', $user_info[0]);
        $rs = $this->db->get('user_blance')->result();
        if ( !$rs ) return false;
        $usr_blance = '';
        foreach ($rs as $k => $v) {
            $usr_blance += $v->blance;
        }
        return $usr_blance;
    
    }
    public function del_blance($user_id)
    {
        if($user_id){
        $rs = $this->db->delete('user_blance', array('user_id'=>$user_id));
        if( !$rs ) return false;
        return true;
        }
        return false;
    }
    
    public function get_goods_chapters($goods_id,$user_id){
        $subjects = get_cache('subjects');
        $chapters = get_cache('chapters');
        //echo $this->db->last_query();
        $tmp_goodsid = implode($goods_id,',');
        
        if ( !$tmp_goodsid ) return false;
        $this->load->model('goods_relation_model');
        $this->db->where_in('good_id', $tmp_goodsid);
        $res = $this->db->get('good_relations')->result();  
        $subject_ids = array(); 
        foreach($res as $key=>$val){
            $subject_ids[] = $subjects[$val->item_id]->children;
        }
        $tmp_subject_ids = implode($subject_ids,',');
        
        if(!$subject_ids) return FALSE;
        return explode(',', $tmp_subject_ids);
    }

    public function update_user_info($user_id, $data){
        $this->db->where('uc_id', $user_id);
        
        if ( !$this->db->update('students', $data) ) return false;
        return true;
    }

    public function byebye($uc_id)
    {
        $this->db->delete('class_history', array('uid' => $uc_id));
        $this->db->delete('comments', array('user_id' => $uc_id));
        $this->db->delete('faqs', array('user_id' => $uc_id));
        $this->db->delete('favorites', array('user_id' => $uc_id));
        // $this->db->delete('grade_relations', array('model' => 'student', 'item_id' => $uc_id));
        $this->db->delete('historys', array('user_id' => $uc_id));
        $this->db->delete('notes', array('user_id' => $uc_id));
        $this->db->delete('papers_simulation', array('model' => 'student', 'item_id' => $uc_id));
        $this->db->delete('question_errors', array('user_id' => $uc_id));
        $this->db->delete('questions_users', array('user_id' => $uc_id));
        // $this->db->delete('student_goods', array('student_id' => $uc_id));
        $this->db->delete('up_downs', array('user_id' => $uc_id));
        $this->db->delete('ups', array('user_id' => $uc_id));
        // $this->db->delete('user_classes', array('user_id' => $uc_id));
        $sql = "delete from uc_members where uid = ".$uc_id;
        $res = $this->db->query($sql);
        // $this->load->helper('uc');
        // $result = uc_user_delete($uc_id);
        if($res){
            $del_row = $this->db->delete('students', array('uc_id' => $uc_id));
            if($del_row){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
        
    }

    public function get_lists($where,$order,$limit,$offset){
        $res = $this->get_by($where,$order,$limit,$offset);
        if(!$res)return false;
        $uids = array();
        $teacherids = array();
        foreach ($res as $key => $value) {
            $uids[] = $value->uc_id;
            if($value->admin_id){
                $teacherids[] = $value->admin_id;
            }
            
        }

        // $this->load->model('student_good_model');
        // $stu_goods = $this->student_good_model->get_goods_ids_by_uc_id($uids);
        $this->load->model('grades_model');
        $user_grades = $this->grades_model->get_stu_grades_console('student',$uids);
        
        if($teacherids){
            $this->load->model('teacher_model');
            $teachers = $this->teacher_model->get_teachers($teacherids);
        }
        foreach($res as $k=>$v){
            $v->grade_name = isset($user_grades[$v->uc_id]) ? $user_grades[$v->uc_id] : '';
            $v->teacher_name = isset($teachers[$v->admin_id]) ? $teachers[$v->admin_id]->name : '';
            // $v->goods = $stu_goods[$v->uc_id];
        }
        return $res;
    }
    
    public function get_uid_by_grades_id($grade_ids){
        $this->db->where_in('grade_id', $grade_ids);
        $this->db->where('model', 'student');
        $rs = $this->db->get('grade_relations')->result();
		//echo $this->db->last_query();exit;
        $user_ids = array();
        foreach($rs as $key=>$val){
            $user_ids[] = $val->item_id;
        }
        return $user_ids;
    }
	
	public function get_uid_by_grade_name($grade_name)
	{
		$this->db->from('grade_relations r');
		$this->db->join('rh_grades g', 'g.id=r.grade_id');
		$this->db->like('g.name', $grade_name);
        $this->db->where('r.model', 'student');
        $this->db->where('g.status !=', 0);
        $this->db->select('DISTINCT(r.item_id)');
		$rs = $this->db->get()->result();
		//echo $this->db->last_query();exit;
        $user_ids = array();
        foreach($rs as $key=>$val){
            $user_ids[] = $val->item_id;
        }
        return $user_ids;
	}
	
    //获取每个地区上直播课的人员
    public function get_uid_by_live_area_id($area_id){
        if(is_array($area_id)){
            $this->db->where_in('live_area_id', $area_id);
        }else{
            $this->db->where('live_area_id',$area_id);
        }
        
        $rs = $this->get_by();
        $user_ids = array();
        foreach($rs as $key=>$val){
            $user_ids[] = $val->uc_id;
        }
        return $user_ids;
    }

    public function get_list_by_ucids($arr,$ids,$order='',$limit='',$offset=''){
        if(is_array($ids)){
            $this->db->where_in('uc_id', $ids);
        }else{
            $this->db->where('uc_id', $ids);
        }
        //$this->db->select('id,name');
        $res = $this->get_by($arr,$order,$limit,$offset);
        return $res;
    }

    public function get_stu_pro($user_ids,$grade_id){
        $this->load->model('grade_relation_model');
        if($user_ids) $this->grade_relation_model->db->where_in('item_id',$user_ids);
        if($grade_id) $this->grade_relation_model->db->where_in('grade_id',$grade_id);
        $res = $this->grade_relation_model->get_by(array('model'=>'student'));
        if(!$res) return false;
        
        // $user = array();
        $user_grades = array();
        foreach ($res as $key => $val) {
            $user_grades[$val->item_id][] = $val->grade_id;
        }
        // print_r($user_grades);
        $stu_progress = array();
        $this->load->model('teacher_classes_model');
        $this->load->model('class_history_model');
        foreach ($user_grades as $key => $val) {
            $this->teacher_classes_model->db->where_in('grade_id',$val);
            $stu_progress[$key] = $this->teacher_classes_model->get_stu_all_class();
            // $this->class_history_model->db->where_in('grade_id',$val);
            // $this->class_history_model->db->where('uid',$key);
            $study_progress[$key] = $this->class_history_model->get_study_progress($key,$val);
        }
        foreach ($stu_progress as $k => $v) {
            foreach ($v as $_k => $_v) {
                $one_study_num = $study_progress[$k][$_k]->study_num;
                $all_num = $_v->class_num;
                $_v->rate = sprintf("%.2f", $one_study_num/$all_num*100);
            }
            // $rate = 
        }
        // print_r($study_progress);
        // print_r($stu_progress);
        return $stu_progress;
    }

    public function get_uids_by_where($where='',$order='',$limit='',$offset=''){
        $res = $this->get_by($where,$order,$limit,$offset);
        if(!$res)return false;
        $uids = array();
        foreach ($res as $key => $value) {
            $uids[] = $value->uc_id;
        }
        return $uids;

    }

    public function get_uids_by_where_for_homework($where='',$order='',$limit='',$offset=''){
        $res = $this->get_by($where,$order,$limit,$offset);
        if(!$res)return false;
        $uids = array();
        foreach ($res as $key => $value) {
            $uids[$value->uc_id] = $value->uc_id;
        }
        return $uids;

    }
    //获通过o2o获得学员CRM中的id 111更新
    public function get_crm_stu_ids_by_where_for_homework($where='',$order='',$limit='',$offset=''){
        $res = $this->get_by($where,$order,$limit,$offset);
        if(!$res)return false;
        $crm_stu_ids = array();
        foreach ($res as $key => $value) {
            $crm_stu_ids[$value->uc_id] = $value->crm_stu_id;
        }
        return $crm_stu_ids;
    }

    /*
    * 更新用户现金券的数量
    * */
    public function update_cashticket($cash_number,$user_id){
        $where['uc_id'] = $user_id;
        $this->db->select('cashticket');
        $user_data = $this->get_one_by($where);
        $new_cashticket = $user_data->cashticket - $cash_number;
        if($new_cashticket >= 0){
            $up_data['cashticket'] = $new_cashticket;
        }else{
            $up_data['cashticket'] = 0;
        }
        return $this->update_where($where,$up_data);
    }
    
    public function get_use_by_mobile($where)
    {
        try 
        {
            $result = $this->db->select('uc_id as id,mobile,name')->where($where)->get($this->table)->row_array();
            return $result;
        }
        catch (Exception $e)
        {
            //echo $e->getMessage();
        }
        
    }
    
    public function get_student_by_mobile($where) {
        return $this->get_one_by($where);
    }
    
    public function get_student_info($stu_id) {
        $sql = "select mobile from rh_students where uc_id = '{$stu_id}'";
        $res = $this->db->query($sql)->result();
        $stu_phone = $res[0]->mobile;
        $crmDb = $this->load->database('crm', true);
        $crm_sql = "SELECT
                        STU_NAME,
                        STU_PHONE,
                        ORG_NAME,
                        SCH_NAME
                    FROM
                        STUDENT S
                    LEFT JOIN STUDENT_SCHOOL SS ON S.STUDENT_ID = SS.STU_ID
                    LEFT JOIN SCHOOL_TABLE ST ON SS.SCH_ID = ST.SCHOOL_TABLE_ID
                    LEFT JOIN ORGAN O ON ST.ORG_ID = O.ORGAN_ID
                    WHERE
                        STU_PHONE = '{$stu_phone}'";
        $stu_info = $crmDb->query($crm_sql)->result();
        return $stu_info[0];
    }

    public function get_user_info($where) {
        $field = "uc_id,mobile,name,nickname,email,gender,birth";
        return $this->db->select($field)->where($where)->get($this->table)->row();
    }
    
    public function get_crm_stu_phone_by_class_id($class_id) {
        if (!$class_id) {
            return array();
        }
        $sql = "SELECT 
                        DISTINCT STUD.STU_PHONE,STUD.STU_NAME
                FROM
                        CLASS_SCHEDULE CLSC,
                        STUDENT_CLASS STCL,
                        STUDENT STUD,
                        CLASS_MANAGE CLMA,
                        COURSE COR
                WHERE
                        CLSC.CLASS_ID = {$class_id}
                AND CLSC.CLASS_ID = STCL.CLASS_ID
                AND TO_DATE (
                        TO_CHAR (
                                CLSC.ATTEND_DATE,
                                'yyyy-MM-dd'
                        ),
                        'yyyy-MM-dd'
                ) >= TO_DATE (
                        TO_CHAR (
                                STCL.CREATE_DATE,
                                'yyyy-MM-dd'
                        ),
                        'yyyy-MM-dd'
                )
                AND STCL.SC_TYPE = 1244
                AND STCL.STU_ID = STUD.STUDENT_ID
                AND CLSC.CLASS_ID = CLMA.OTM_CLASS_ID
                AND CLMA.COURSE_ID = COR.COURSE_ID";
        $crmDb = $this->load->database('crm', true);
        return $crmDb->query($sql)->result_array();
    }
    
    public function get_student_by_mobiles($mobiles) {
        $this->db->where_in('mobile', $mobiles);
        return $this->get_by('', 'id desc');
    }
}