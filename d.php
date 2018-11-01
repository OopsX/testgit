<?php if(!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 教师开课模型
 * @package tikuzi
 * @category models
 * @author TIKUZI DEV TEAM
 * @copyright 2013 (c) tikuzi.com all rights reserved.
 */
class Teacher_classes_model extends MY_Model {

    /**
     * 上课时间startdate 后8天为家庭作业完成截止时间
     */
    const HOME_WORK_INTERVAL = 691200;

    public $table = 'teacher_classes';

    public function get_class_list($where,$order='id desc',$limit=0,$offset=0){
        
        if($where) $this->db->where($where);
        if($order) $this->db->order_by($order);
        if($limit && $offset) $this->db->limit($limit, $offset); elseif($limit) $this->db->limit($limit);
        $this->db->select();
        $this->db->group_by('classes_id');
        return $this->get_by(array('status' => 99));
    }
    public function get_search_lists($chapetr_ids,$where=''){
        if(empty($chapetr_ids) || !is_array($chapetr_ids)) return false;
        $this->db->where_in("chapter_id", $chapetr_ids);
        return $this->get_by($where, '', 0, 0, false);
    }

    public function get_list_by_where($class_id='',$teacher_id='',$subject_id='',$start_time=''){
        if($teacher_id) $where['teacher_id'] = $teacher_id;
        if($subject_id) $where['subject_id'] = $subject_id;
        if($class_id) $where['class_id'] = $class_id;
        if($start_time) {
            $where['created >= '] = strtotime($start_time);
            $where['created <= '] = strtotime($start_time+1);
        } 
        if($where) $where = implode(' and ', $where);
        $this->get_by($where,'id desc');
    }

    public function get_class_ids($where,$order='id desc',$limit=0,$offset=0){
        if($where) $this->db->where($where);
        if($order) $this->db->order_by($order);
        if($limit && $offset) $this->db->limit($limit, $offset); elseif($limit) $this->db->limit($limit);
        $this->db->select('classes_id');
        $this->db->group_by('classes_id');
        $res = $this->get_by(array('status' => 99));
        $class_ids = array();
        foreach ($res as $key => $val) {
            $class_ids[] = $val->classes_id;
        }
        return $class_ids;
    }

    public function get_stu_all_class($where,$order='id desc',$limit=0,$offset=0){
        if($where) $this->db->where($where);
        if($order) $this->db->order_by($order);
        if($limit && $offset) $this->db->limit($limit, $offset); elseif($limit) $this->db->limit($limit);
        $this->db->select('count(id) as class_num,subject_id');
        $this->db->group_by('subject_id');
        $res = $this->get_by(array('status' => 99));
        // echo $this->db->last_query();
        $new_lists = array();
        foreach ($res as $key => $val) {
            $new_lists[$val->subject_id] = $val;
        }
        return $new_lists;
    }

    public function total($where){
        if($this->status_and_time && $this->find_status != false){
            if(!isset($where["status"]) && is_array($where)){
                $where["status"] = $this->find_status;
            }else if(is_string($where) && $where){
                $where .= ' AND `status` = '.$this->find_status;
            }
        }
        if($where) $this->db->where($where);
        $this->db->where('status',99);
        $this->db->from($this->table);
        // $this->db->group_by('classes_id');
        $query = $this->db->get();
        if($query->num_rows() > 0) return $query->num_rows();

    }
    public function total_class($where){
        if($this->status_and_time && $this->find_status != false){
            if(!isset($where["status"]) && is_array($where)){
                $where["status"] = $this->find_status;
            }else if(is_string($where) && $where){
                $where .= ' AND `status` = '.$this->find_status;
            }
        }
        if($where) $this->db->where($where);
        $this->db->from($this->table);
        $this->db->group_by('classes_id');
        $query = $this->db->get();
        if($query->num_rows() > 0) return $query->num_rows();
    }
    /**
     * CRM同步O2O出错，老师取rh_grades_timetable表中的数据
     * 通过grades取出crm_id取出teacher_mobile
     */
    public function get_lists($where,$order='a.id desc', $limit=0, $offset=0,$student_ids=''){
        /*
		$this->db->select('teacher_id,classes_id,area_id,school_id,subject_id,created,catalog_id,grade_id,chapter_id,startdate,time,homedate,crm_complete_rate,crm_correct_rate,crm_complete_count,crm_check_rate,grade_user,check_user,actual_user,kaoqinglv');
        if($where) $this->db->where($where);
        if($order) $this->db->order_by($order);
        if ($limit && $offset) $this->db->limit($limit, $offset); elseif ($limit) $this->db->limit($limit);
        // $this->db->group_by('classes_id');[kaoqinglv] => 71.43 [mobile] => 15926260060
        $res = $this->get_by(array('status' => 99));*/
		/* 此处要求去掉未布置作业的班级数据，采用关联 rh_classes 表的方式进行筛选。xiangrong 2018-09-05 */
		$where = preg_replace('/`(\w+)`/', 'a.`$1`', $where);
		$segment = 'id,teacher_id,classes_id,area_id,school_id,subject_id,created,catalog_id,grade_id,chapter_id,startdate,time,homedate,crm_complete_rate,crm_correct_rate,crm_complete_count,crm_check_rate,grade_user,check_user,actual_user,kaoqinglv';
		$segment = str_replace(',', ',a.', $segment);
		if($order) $order_str = $order; else $order_str = 'a.id desc';
		if ($limit) $limit_str = " limit " . $offset . "," . $limit; else $limit_str = '';
		
		$res = $this->db->query("select a." . $segment . " from rh_teacher_classes as a INNER JOIN rh_classes as b on a.classes_id=b.id where " . $where . " and a.status=99 and b.status=99 and ((b.homework_paper_id is not NULL and b.homework_paper_id<>0) or b.manual_questions!='' ) ORDER BY " . $order_str . $limit_str )->result();
		
		$total = $this->db->query("select count(*) as ct from rh_teacher_classes as a INNER JOIN rh_classes as b on a.classes_id=b.id where " . $where . " and a.status=99 and b.status=99 and ((b.homework_paper_id is not NULL and b.homework_paper_id<>0) or b.manual_questions!='' ) ")->result();
		/* over */
        $this->load->model('grades_model');

        foreach ($res as &$value) {

            $time = $value->time;
            $grade_id = $value->grade_id;
            $this->load->model('grades_model');
            $mobile = $this->grades_model->get_crm_id_for_grade_id($grade_id, $time);
            $value->mobile = trim($mobile[0]);
            if ($mobile) {
                $this->load->model('teacher_model');
                $teacher = $this->teacher_model->get_teacher_id_for_mobile($value->mobile);
//            $teacher_ids[] = $teacher->id;
                $value->teacher_id = $teacher->id;
//                $value->sql = $this->teacher_model->db->last_query();
            }
        }
//        return $res;

        // echo $this->db->last_query();//die;
//        $mobiles = array();
        $teacher_ids = array();
        $class_ids = array();
        $school_ids = array();
        $grade_ids = array();
        $area_ids = array();
        $teachers = '';
        $schools = '';
        $grades = '';
        $history_list = array();
        $hislists = array();
        $grade_user = array();
        $crm_finish_rate = array();
        $crm_correct_rate = array();
        $kapqinlv = array();
        $tmp_hislists = array();
        $finish_rate = array();
        $finish_user = array();
        $correct_rate = array();
        $kaoqinlv = array();
        $this->load->model('grades_model');
        if ($res) {
            foreach ($res as $key => $val) {
				/*
                if (true !== $this->grades_model->get_grade_homework($val->classes_id)) {
                    continue;
                }
				*/
				/* 
				为何注释掉此处，因为涉及到分页，若查询完成后再在循环中去除数据，则数据总量会发生明显变化，将导致后面的页面分页无效甚至404错误。
				故在上方采用关联查询筛选无效数据。 xiangrong 2018-09-05
				*/
                $teacher_ids[] = $val->teacher_id;
                $class_ids[] = $val->classes_id;
                $school_ids[] = $val->school_id;
                $grade_ids[] = $val->grade_id;
                $area_ids[] = $val->area_id;

                $this->load->model('homework_model');
                $this->load->model('grade_relation_model');
                $relations = $this->grade_relation_model->get_relation_by_grade_id($val->grade_id, 'student');
                $student_ids = array();
                $hislist = '';
                if ($relations) {
                    foreach ($relations as $_k => $_v) {
                        $student_ids[] = $_v->item_id;
                    }
                    $grade_user[$val->grade_id] = count($student_ids);
                    $this->load->model('history_model');
                    $one_grade = $this->grades_model->get_one($val->grade_id);
                    $related_grade = $this->grade_relation_model->get_one_by(array('model' => 'grade', 'item_id' => $val->grade_id));
                    if ($related_grade) {
                        $big_grade_id = $related_grade->grade_id;
                        $arr_where = array($big_grade_id, $val->grade_id);
                    } else {
                        $arr_where = $val->grade_id;
                    }

                    if ($one_grade && $one_grade->crm_id > 0 && $val->time > 0) {
                        $crm_data = $this->get_crm_data_for_grade($one_grade, $val);
                        // print_r($crm_data);
                        //建班人数  
                        $grade_user_ids = $crm_data['grade_user_ids'];
                        //扫码人数(报班)
                        $check_user_ids = $crm_data['check_user_ids'];
                        //扫码人数(串班)
                        $check_users_ids = $crm_data['check_users_ids'];
                        //实到人数
                        $actual_user_ids = $crm_data['actual_user_ids'];
                        //试听人数
                        $listen_user_ids = $crm_data['listen_user_ids'];
                        //试听成功人数
                        $listen_success_user_ids = $crm_data['listen_success_user_ids'];
                        $hislist = $this->history_model->get_lists_for_admin($val->classes_id, $check_user_ids, 6, $arr_where, $val->homedate);
                        //作业完成人数
                        $finish_user[$val->grade_id][$val->classes_id] = $hislist['total_his_user'] ? $hislist['total_his_user'] : 0;

                        $grade_user_count[$val->grade_id][$val->classes_id] = $grade_user_ids ? count($grade_user_ids) : 0;
                        $check_user_count[$val->grade_id][$val->classes_id] = $check_user_ids ? count($check_user_ids) : 0;
                        $check_user_counts[$val->grade_id][$val->classes_id] = $check_users_ids ? count($check_users_ids) : 0;
                        $actual_user_count[$val->grade_id][$val->classes_id] = $actual_user_ids ? $actual_user_ids : 0;
                        $listen_user_count[$val->grade_id][$val->classes_id] = $listen_user_ids ? count($listen_user_ids) : 0;
                        $listen_success_user_count[$val->grade_id][$val->classes_id] = $listen_success_user_ids ? count($listen_success_user_ids) : 0;

                        //作业完成率
                        $finish_rate[$val->grade_id][$val->classes_id] = $hislist['answered'] ? round(($hislist['answered'] / ($hislist['questions'] * $check_user_count[$val->grade_id][$val->classes_id])) * 100, 2) : 0;

                        //作业正确率
                        $correct_rate[$val->grade_id][$val->classes_id] = $hislist['total_score'] ? round(($hislist['total_score'] / ($hislist['total_his_user'] * 100)) * 100, 2) : 0;
                        //旧考勤率
                        $kaoqinlv[$val->grade_id][$val->classes_id] = $check_user_count[$val->grade_id][$val->classes_id] ? round(($check_user_count[$val->grade_id][$val->classes_id] / $grade_user_count[$val->grade_id][$val->classes_id]) * 100, 2) : 0;
                        //新考勤率1
//                        $kaoqinlv[$val->grade_id][$val->classes_id] = $check_user_count[$val->grade_id][$val->classes_id] ? round((($check_user_count[$val->grade_id][$val->classes_id]+$check_user_counts[$val->grade_id][$val->classes_id])/$actual_user_count[$val->grade_id][$val->classes_id])*100,2) : 0;
                    }
                }
                $this->load->model('history_model');
                $this->load->model('teacher_classes_model');
                // $tmp_hislists[$val->grade_id][$val->classes_id] = $this->history_model->get_by_class_ids_type($val->classes_id,$student_ids,6);
                $hislists[$val->grade_id][$val->classes_id] = $this->teacher_classes_model->get_one_by(array('grade_id' => $val->grade_id, 'classes_id' => $val->classes_id, 'teacher_id' => $val->teacher_id));
            }
            $this->load->model('teacher_model');
            $this->load->model('classes_model');
            $this->load->model('school_model');
            $this->load->model('areas_model');

            $teachers = $this->teacher_model->get_teachers($teacher_ids);
            $schools = $this->school_model->get_by_ids('', $school_ids);
            $grades = $this->grades_model->get_by_ids_for_teach_info('', $grade_ids);
            $areas = $this->areas_model->get_areas_by_ids();

            $tmp_classes = $this->classes_model->get_by_ids('', $class_ids);
            $classes = array();
            foreach ($tmp_classes as $key => $val) {
                $classes[$val->id] = $val;
            }

        }
        // print_r($crm_correct_rate);
        $data = array(
            'history_list' => $hislists,
            'teachers' => $teachers,
            'schools' => $schools,
            'grades' => $grades,
            'areas' => $areas,
            'classes' => $classes,
            'lists' => $res,
            'grade_user' => $grade_user,
            'finish_user' => $finish_user,
            'grade_user_count' => $grade_user_count,
            'check_user_count' => $check_user_count,
            'actual_user_count' => $actual_user_count,
            'finish_rate' => $finish_rate,
            'correct_rate' => $correct_rate,
			'total' => $total[0]->ct,
            'kaoqinlv' => $kaoqinlv
        );
        return $data;
    }

    public function get_crm_data_for_grade($grade, $homework_data)
    {
        $crmDb = $this->load->database('crm', true);
        // 每次课的建班人数（或者叫班级人数），参数  CLASS_ID = 22976:crm 中的班级id=22976， about=3：第3次课
        $crm_id = $grade->crm_id;
        $time = $homework_data->time;
        //班级人数
        $grade_key = 'count:grade_id:' . $crm_id . 'about:' . $time;
        //扫码人数-有效学员
        $check_valid_key = 'count:check_valid_id:' . $crm_id . 'about:' . $time;
        //扫码人数-串班学员
        $check_string_key = 'count:check_string_id:' . $crm_id . 'about:' . $time;
        //实到人数
        $actual_key = 'count:actual_id:' . $crm_id . 'about:' . $time;
        //试听人数
        $listen_key = 'count:listen_id:' . $crm_id . 'about:' . $time;
        //试听成功人数
        $listen_success_key = 'count:listen_success_id:' . $crm_id . 'about:' . $time;
        //最新的班级人数sql
        $grade_datas = $this->redis->get($grade_key);
        $grade_user_ids = array();
        if ($grade_datas) {
            $grade_user_ids = unserialize($grade_datas);
        } else {
            $grade_user_sql = "SELECT DISTINCT
                STUD.STU_PHONE
            FROM
                CLASS_SCHEDULE CLSC
                ,STUDENT_CLASS STCL
                ,STUDENT STUD
                ,CLASS_MANAGE CLMA
                ,COURSE COR
                ,CLASS_SCHEDULE CLSC2
                ,CLASS_SCHEDULE_STUDENT CSST
            WHERE
                CLSC.CLASS_ID = " . $crm_id . "
            AND CLSC.ABOUT = " . $time . "
            AND CLSC.CLASS_ID = STCL.CLASS_ID "
                /*
                AND TO_DATE(TO_CHAR(CLSC.ATTEND_DATE,'yyyy-MM-dd'),'yyyy-MM-dd') >= TO_DATE (TO_CHAR(STCL.CREATE_DATE,'yyyy-MM-dd'),'yyyy-MM-dd')
                */
                . " AND (TO_DATE(TO_CHAR(CLSC.ATTEND_DATE,'yyyy-MM-dd'),'yyyy-MM-dd') >= TO_DATE (TO_CHAR(STCL.CREATE_DATE,'yyyy-MM-dd'),'yyyy-MM-dd') 
			OR TO_DATE(TO_CHAR(CLSC.ATTEND_DATE,'yyyy-MM-dd'),'yyyy-MM-dd') = TO_DATE (TO_CHAR(CSST.CREATE_DATE,'yyyy-MM-dd'),'yyyy-MM-dd')) " .
                /*
                reason: 因为有bug影响，老师删除掉学员的班级之后又重新添加了班级，导致STUDENT_CLASS表的创建时间大于上课时间，影响了老师的考勤绩效，特此记录
                日后若能对STUDENT_CLASS表的创建时间>签到时间的记录重新处理，可修复此bug
                修改人：xiangrong@qq.com 18-08-17
                */
                " AND STCL.SC_TYPE = 1244
            AND STCL.STU_ID = STUD.STUDENT_ID
            AND CLSC.CLASS_ID=CLMA.OTM_CLASS_ID
            AND CLMA.COURSE_ID=COR.COURSE_ID
            AND CLSC.CLASS_ID=CLSC2.CLASS_ID AND ((CLSC2.ABOUT<=ceil(COR.KC * 0.3 + 1) AND " . $time . ">ceil(COR.KC * 0.3 + 1)) OR (" . $time . "<=ceil(COR.KC * 0.3 + 1) AND CLSC2.ABOUT<=" . $time . "))
            AND CLSC2.C_S_ID=CSST.CS_ID
            AND STUD.STUDENT_ID=CSST.STU_ID "
			/*
				新增条件 IS_OTOPP IS NULL 
				reason:
				教学管理部新需求,O2O后台关于家庭作业建班学员的算法，要增加一个过滤场景。
				场景描述：新报名的学员，如果为了提前看视频与作业，校区一般会将该学员加入到相同科目的往期班级中，
				往期班级可能是正在上课的班级或者是已经结课的班级，这种学员会从往期班级的第一讲扫码加入，
				这时候会产生一条考勤记录(IS_OTOPP IS NOT NULL)。
				这种学员不属于该班级的建班学员，所以要过滤掉。
				xiangrong 2018-09-21
			*/
			. "AND CSST.IS_OTOPP IS NULL";

            $grade_user = $crmDb->query($grade_user_sql)->result();
            $this->load->model('student_model');
            $grade_user_phone = array();
            if ($grade_user) {
                foreach ($grade_user as $key => $val) {
                    $grade_user_phone[] = $val->STU_PHONE;
                }
                $this->student_model->db->where_in('mobile', $grade_user_phone);
                $grade_user_ids = $this->student_model->get_uids_by_where_for_homework();
                $grade_data = serialize($grade_user_ids);
                $this->redis->set($grade_key, $grade_data);
                //$this->redis->expire($grade_key,86400);
                $this->redis->expire($grade_key, 3600);
            }
        }
        //最新扫码人数（有效扫码人数）
        $check_valid_datas = $this->redis->get($check_valid_key);
        $check_user_ids = array();
        if ($check_valid_datas) {
            $check_user_ids = unserialize($check_valid_datas);
        } else {
            $check_user_sql = "SELECT DISTINCT
                STUD.STU_PHONE
            FROM
                CLASS_SCHEDULE CLSC
                ,STUDENT_CLASS STCL
                ,STUDENT STUD
                ,CLASS_MANAGE CLMA
                ,COURSE COR
                ,CLASS_SCHEDULE CLSC2
                ,CLASS_SCHEDULE_STUDENT CSST
                ,CLASS_SCHEDULE_STUDENT CSST2
            WHERE
                CLSC.CLASS_ID = " . $crm_id . "
            AND CLSC.ABOUT = " . $time . "
            AND CLSC.CLASS_ID = STCL.CLASS_ID "
                /*
                AND TO_DATE(TO_CHAR(CLSC.ATTEND_DATE,'yyyy-MM-dd'),'yyyy-MM-dd') >= TO_DATE (TO_CHAR(STCL.CREATE_DATE,'yyyy-MM-dd'),'yyyy-MM-dd')
                */
                . " AND (TO_DATE(TO_CHAR(CLSC.ATTEND_DATE,'yyyy-MM-dd'),'yyyy-MM-dd') >= TO_DATE (TO_CHAR(STCL.CREATE_DATE,'yyyy-MM-dd'),'yyyy-MM-dd') 
			OR TO_DATE(TO_CHAR(CLSC.ATTEND_DATE,'yyyy-MM-dd'),'yyyy-MM-dd') = TO_DATE (TO_CHAR(CSST.CREATE_DATE,'yyyy-MM-dd'),'yyyy-MM-dd')) " .
                /*
                reason: 因为有bug影响，老师删除掉学员的班级之后又重新添加了班级，导致STUDENT_CLASS表的创建时间大于上课时间，影响了老师的考勤绩效，特此记录
                日后若能对STUDENT_CLASS表的创建时间>签到时间的记录重新处理，可修复此bug
                修改人：xiangrong@qq.com 18-08-17
                */
                " AND STCL.SC_TYPE = 1244
            AND STCL.STU_ID = STUD.STUDENT_ID
            AND CLSC.CLASS_ID=CLMA.OTM_CLASS_ID
            AND CLMA.COURSE_ID=COR.COURSE_ID
            AND CLSC.CLASS_ID=CLSC2.CLASS_ID AND ((CLSC2.ABOUT<=ceil(COR.KC * 0.3 + 1) AND " . $time . ">ceil(COR.KC * 0.3 + 1)) OR (" . $time . "<=ceil(COR.KC * 0.3 + 1) AND CLSC2.ABOUT<=" . $time . "))
            AND CLSC2.C_S_ID=CSST.CS_ID
            AND STUD.STUDENT_ID=CSST.STU_ID
            AND CLSC.C_S_ID=CSST2.CS_ID AND STUD.STUDENT_ID=CSST2.STU_ID "
			/*
				新增条件 IS_OTOPP IS NULL 
				reason:
				教学管理部新需求,O2O后台关于家庭作业建班学员的算法，要增加一个过滤场景。
				场景描述：新报名的学员，如果为了提前看视频与作业，校区一般会将该学员加入到相同科目的往期班级中，
				往期班级可能是正在上课的班级或者是已经结课的班级，这种学员会从往期班级的第一讲扫码加入，
				这时候会产生一条考勤记录(IS_OTOPP IS NOT NULL)。
				这种学员不属于该班级的建班学员，所以要过滤掉。
				xiangrong 2018-09-21
			*/
			. "AND CSST.IS_OTOPP IS NULL";

            //echo $check_user_sql;exit;
            $check_user = $crmDb->query($check_user_sql)->result();
            $check_user_phone = array();

            if ($check_user) {
                foreach ($check_user as $_key => $_val) {
                    $check_user_phone[] = $_val->STU_PHONE;
                }
                $this->student_model->db->where_in('mobile', $check_user_phone);
                $check_user_ids = $this->student_model->get_uids_by_where_for_homework();
                $check_valid_data = serialize($check_user_ids);
                $this->redis->set($check_valid_key, $check_valid_data);
                //$this->redis->expire($check_valid_key,86400);
                $this->redis->expire($check_valid_key, 3600);
            }
        }

        //最新扫码人数（串班学员）1
        $check_string_datas = $this->redis->get($check_string_key);
        $check_users_ids = array();
        if ($check_string_datas) {
            $check_users_ids = unserialize($check_string_datas);
        } else {
            $check_users_sql = "SELECT DISTINCT
                            STUD.STU_PHONE
                        FROM
                            STUDENT STUD
                        WHERE
                            STUDENT_ID IN (
                                SELECT DISTINCT
                                    STCL.STU_ID
                                FROM
                                    CLASS_SCHEDULE CLSC,
                                    STUDENT_CLASS STCL,
                                    CLASS_SCHEDULE_STUDENT CSST
                                WHERE
                                    CLSC.CLASS_ID = " . $crm_id . "
                                AND CLSC.ABOUT = " . $time . "
                                AND CLSC.CLASS_ID = STCL.CLASS_ID "
                /*
                AND TO_DATE(TO_CHAR(CLSC.ATTEND_DATE,'yyyy-MM-dd'),'yyyy-MM-dd') >= TO_DATE (TO_CHAR(STCL.CREATE_DATE,'yyyy-MM-dd'),'yyyy-MM-dd')
                */
                . " AND (TO_DATE(TO_CHAR(CLSC.ATTEND_DATE,'yyyy-MM-dd'),'yyyy-MM-dd') >= TO_DATE (TO_CHAR(STCL.CREATE_DATE,'yyyy-MM-dd'),'yyyy-MM-dd') 
								OR TO_DATE(TO_CHAR(CLSC.ATTEND_DATE,'yyyy-MM-dd'),'yyyy-MM-dd') = TO_DATE (TO_CHAR(CSST.CREATE_DATE,'yyyy-MM-dd'),'yyyy-MM-dd')) " .
                /*
                reason: 因为有bug影响，老师删除掉学员的班级之后又重新添加了班级，导致STUDENT_CLASS表的创建时间大于上课时间，影响了老师的考勤绩效，特此记录
                日后若能对STUDENT_CLASS表的创建时间>签到时间的记录重新处理，可修复此bug
                修改人：xiangrong@qq.com 18-08-17
                */
                " AND STCL.SC_TYPE = 1245
                                AND CLSC.C_S_ID = CSST.CS_ID
                                AND STCL.STU_ID = CSST.STU_ID
                            )";
            //echo $check_user_sql;
            $check_users = $crmDb->query($check_users_sql)->result();
            $check_users_phone = array();
            if ($check_users) {
                foreach ($check_users as $_key => $_value) {
                    $check_users_phone[] = $_value->STU_PHONE;
                }
                $this->student_model->db->where_in('mobile', $check_users_phone);
                $check_users_ids = $this->student_model->get_uids_by_where_for_homework();
                $check_string_data = serialize($check_users_ids);
                $this->redis->set($check_string_key, $check_string_data);
                //$this->redis->expire($check_string_key,86400);
                $this->redis->expire($check_string_key, 3600);
            }
        }


        // 实到人数  参数  CLASS_ID = 22976:crm 中的班级id=22976， about=3：第3次课
        $actual_datas = $this->redis->get($actual_key);
        if ($actual_datas) {
            $actual_user_ids = $actual_datas;
        } else {
            $actual_user_sql = "SELECT SD_NUMBER FROM CLASS_SCHEDULE WHERE CLASS_ID = " . $crm_id . " and ABOUT=" . $time;
            $actual_user = $crmDb->query($actual_user_sql)->result();
            // print_r($actual_user);
            $actual_user_phone = array();
            if ($actual_user) {
                $actual_user_ids = $actual_user[0]->SD_NUMBER;
                $this->redis->set($actual_key, $actual_user_ids);
                //$this->redis->expire($actual_key,86400);
                $this->redis->expire($actual_key, 3600);
            }
        }


        //试听数
        $listen_datas = $this->redis->get($listen_key);
        $listen_user_ids = array();
        if ($listen_datas) {
            $listen_user_ids = unserialize($listen_datas);
        } else {
            $listen_sql = "SELECT DISTINCT STUD.STU_PHONE FROM STUDENT STUD
WHERE STUDENT_ID in (
SELECT DISTINCT COTR.STU_ID FROM CLASS_SCHEDULE CLSC,COURSETRY COTR WHERE CLSC.CLASS_ID = " . $crm_id . " and CLSC.ABOUT=" . $time . " AND CLSC.C_S_ID=COTR.CS_ID)";
            $listen_user = $crmDb->query($listen_sql)->result();
            $listen_user_phone = array();

            if ($listen_user) {
                foreach ($listen_user as $_key => $_val) {
                    $listen_user_phone[] = $_val->STU_PHONE;
                }
                $this->student_model->db->where_in('mobile', $listen_user_phone);
                $listen_user_ids = $this->student_model->get_uids_by_where_for_homework();
                $listen_data = serialize($listen_user_ids);
                $this->redis->set($listen_key, $listen_data);
                //$this->redis->expire($listen_key,86400);
                $this->redis->expire($listen_key, 3600);
            }
        }


        //试听数成功数
        $listen_success_user_ids = array();
        $listen_success_datas = $this->redis->get($listen_success_key);
        if ($listen_success_datas) {
            $listen_success_user_ids = unserialize($listen_success_datas);
        } else {
            $listen_success_sql = "SELECT DISTINCT STUD.STU_PHONE FROM STUDENT STUD
WHERE STUDENT_ID in (
SELECT DISTINCT COTR.STU_ID FROM CLASS_SCHEDULE CLSC,COURSETRY COTR, ORDER_FORM ORFO 
WHERE CLSC.CLASS_ID = " . $crm_id . " and CLSC.ABOUT=" . $time . " AND CLSC.C_S_ID=COTR.CS_ID AND COTR.COURSETRY_ID=ORFO.COURSETRY_ID
)";
            $listen_success_user = $crmDb->query($listen_success_sql)->result();
            $listen_success_user_phone = array();
            if ($listen_success_user) {
                foreach ($listen_success_user as $_key => $_val) {
                    $listen_success_user_phone[] = $_val->STU_PHONE;
                }
                $this->student_model->db->where_in('mobile', $listen_success_user_phone);
                $listen_success_user_ids = $this->student_model->get_uids_by_where_for_homework();
                $listen_success_data = serialize($listen_success_user_ids);
                $this->redis->set($listen_success_key, $listen_success_data);
                //$this->redis->expire($listen_success_key,86400);
                $this->redis->expire($listen_success_key, 3600);
            }
        }
        return array('grade_user_ids' => $grade_user_ids, 'check_user_ids' => $check_user_ids, 'actual_user_ids' => $actual_user_ids, 'listen_user_ids' => $listen_user_ids, 'listen_success_user_ids' => $listen_success_user_ids, 'check_users_ids' => $check_users_ids);
    }

    public function get_after_class_lists($where, $order = 'id desc', $limit = 0, $offset = 0, $student_ids = '')
    {
        $this->db->select('teacher_id,classes_id,area_id,school_id,subject_id,created,catalog_id,grade_id,chapter_id,startdate,time,homedate,crm_afterclass_complete_rate,crm_afterclass_correct_rate,crm_check_rate');
        if ($where) $this->db->where($where);
        if ($order) $this->db->order_by($order);
        if ($limit && $offset) $this->db->limit($limit, $offset); elseif ($limit) $this->db->limit($limit);
        // $this->db->group_by('classes_id');
        $res = $this->get_by(array('status' => 99));
        // echo $this->db->last_query();//die;
        $teacher_ids = array();
        $class_ids = array();
        $school_ids = array();
        $grade_ids = array();
        $teachers = '';
        $schools = '';
        $grades = '';
        $history_list = array();
        $hislists = array();
        $grade_user = array();
        $crm_finish_rate = array();
        $crm_correct_rate = array();
        $kapqinlv = array();
        $finish = array();
        $this->load->model('grades_model');


        if ($res) {
            foreach ($res as $key => $val) {
                $relations = array();
                $teacher_ids[] = $val->teacher_id;
                $class_ids[] = $val->classes_id;
                $school_ids[] = $val->school_id;
                $grade_ids[] = $val->grade_id;
                $this->load->model('grade_relation_model');
                $this->load->model('homework_model');
                $relations = $this->grade_relation_model->get_relation_by_grade_id($val->grade_id, 'student');
                $student_ids = array();
                $this->load->model('history_model');
                if ($relations) {
                    foreach ($relations as $_k => $_v) {
                        $student_ids[] = $_v->item_id;
                    }
                    $grade_user[$val->grade_id] = count($student_ids);
                    $one_grade = $this->grades_model->get_one($val->grade_id);
                    $related_grade = $this->grade_relation_model->get_one_by(array('model' => 'grade', 'item_id' => $val->grade_id));
                    if ($related_grade) {
                        $big_grade_id = $related_grade->grade_id;
                        $arr_where = array($big_grade_id, $val->grade_id);
                    } else {
                        $arr_where = $val->grade_id;
                    }

                    if ($one_grade && $one_grade->crm_id > 0 && $val->time > 0) {
                        if (!$crm_data = $this->get_crm_data($val)) {
                            $crm_data = $this->get_crm_data_for_grade($one_grade, $val);
                            $this->insert_crm_data($val, $crm_data);
                        }

                        //建班人数
                        $grade_user_ids = $crm_data['grade_user_ids'];
                        //扫码人数(正式班级)
                        $check_user_ids = $crm_data['check_user_ids'];
                        //扫码人数(串班班级)
                        $check_users_ids = $crm_data['check_users_ids'];
                        //实到人数
                        $actual_user_ids = $crm_data['actual_user_ids'];
                        //试听人数
                        $listen_user_ids = $crm_data['listen_user_ids'];
                        //试听成功人数
                        $listen_success_user_ids = $crm_data['listen_success_user_ids'];

                        $hislist = $this->history_model->get_lists_for_admin($val->classes_id, $check_user_ids, 10, $arr_where, $val->startdate);
                        //作业完成人数
                        //print_r($hislist);
                        $finish_user[$val->grade_id][$val->classes_id] = $hislist['total_his_user'] ? $hislist['total_his_user'] : 0;

                        $grade_user_count[$val->grade_id][$val->classes_id] = $grade_user_ids ? count($grade_user_ids) : 0;
                        $check_user_count[$val->grade_id][$val->classes_id] = $check_user_ids ? count($check_user_ids) : 0;
                        $check_user_counts[$val->grade_id][$val->classes_id] = $check_users_ids ? count($check_users_ids) : 0;
                        $actual_user_count[$val->grade_id][$val->classes_id] = $actual_user_ids ? $actual_user_ids : 0;
                        $listen_user_count[$val->grade_id][$val->classes_id] = $listen_user_ids ? count($listen_user_ids) : 0;
                        $listen_success_user_count[$val->grade_id][$val->classes_id] = $listen_success_user_ids ? count($listen_success_user_ids) : 0;

                        //作业完成率
                        $finish_rate[$val->grade_id][$val->classes_id] = $hislist['answered'] ? round(($hislist['answered'] / ($hislist['questions'] * $check_user_count[$val->grade_id][$val->classes_id])) * 100, 2) : 0;

                        //作业正确率
                        $correct_rate[$val->grade_id][$val->classes_id] = $hislist['total_score'] ? round(($hislist['total_score'] / ($hislist['total_his_user'] * 100)) * 100, 2) : 0;
                        //旧考勤率
                        $kaoqinlv[$val->grade_id][$val->classes_id] = $check_user_count[$val->grade_id][$val->classes_id] ? round(($check_user_count[$val->grade_id][$val->classes_id] / $grade_user_count[$val->grade_id][$val->classes_id]) * 100, 2) : 0;
                        //新考勤率1
//                        $kaoqinlv[$val->grade_id][$val->classes_id] = $check_user_count[$val->grade_id][$val->classes_id] ? round((($check_user_count[$val->grade_id][$val->classes_id]+$check_user_counts[$val->grade_id][$val->classes_id])/$actual_user_count[$val->grade_id][$val->classes_id])*100,2) : 0;

                    }
                    $this->load->model('teacher_classes_model');
                    $hislists[$val->grade_id][$val->classes_id] = $this->history_model->get_by_class_ids_type($val->classes_id, $student_ids, 10, $val->grade_id);
                    // $hislists[$val->grade_id][$val->classes_id] = $this->teacher_classes_model->get_one_by(array('grade_id'=>$val->grade_id,'classes_id'=>$val->classes_id));
                }
            }
            // var_dump($this->history_model->db->last_query());die;   
            // foreach ($tmp_hislists as $key => $val) {
            //     // $count_question += $val->question;
            //     $tmp_list = array();
            //     $new_tmp_list = array();
            //     if($val){
            //         foreach ($val as $_m => $_n) {
            //             foreach ($_n as $_k => $_v) {
            //                 $tmp_list['questions'] += $_v->questions;
            //                 $tmp_list['correct'] += $_v->corrects;
            //             }
            //             $new_tmp_list[$_m] = $tmp_list;
            //             $new_tmp_list[$_m]['user'] =$_n ? count($_n) : 0;
            //         }
            //     }
            //         $hislists[$key] = $new_tmp_list;
            // }
            // print_r($hislists);die;
// print_r($grade_user);die;
            $this->load->model('teacher_model');
            $this->load->model('classes_model');
            $this->load->model('school_model');
            $teachers = $this->teacher_model->get_teachers($teacher_ids);
            $schools = $this->school_model->get_by_ids('', $school_ids);
            $grades = $this->grades_model->get_by_ids_for_teach_info('', $grade_ids);
            $tmp_classes = $this->classes_model->get_by_ids('', $class_ids);
            $classes = array();
            foreach ($tmp_classes as $key => $val) {
                $classes[$val->id] = $val;
            }
        }
        // print_r($classes);
        $data = array(
            'history_list' => $hislists,
            'teachers' => $teachers,
            'schools' => $schools,
            'grades' => $grades,
            'classes' => $classes,
            'lists' => $res,
            'grade_user' => $grade_user,
            'finish_user' => $finish_user,
            'grade_user_count' => $grade_user_count,
            'check_user_count' => $check_user_count,
            'actual_user_count' => $actual_user_count,
            'finish_rate' => $finish_rate,
            'correct_rate' => $correct_rate,
            'kaoqinlv' => $kaoqinlv
        );
        return $data;
    }

    //获取随堂练习的数据
    public function get_classwork_lists($where, $order = 'id desc', $limit = 0, $offset = 0, $student_ids = '')
    {
        $this->db->select('teacher_id,classes_id,area_id,school_id,subject_id,created,catalog_id,grade_id,chapter_id,startdate,time,homedate,crm_classwork_complete_rate,crm_classwork_correct_rate,crm_check_rate');
        if ($where) $this->db->where($where);
        if ($order) $this->db->order_by($order);
        if ($limit && $offset) $this->db->limit($limit, $offset); elseif ($limit) $this->db->limit($limit);
        // $this->db->group_by('classes_id');
        $res = $this->get_by(array('status' => 99));

        if ( "debug" == $_GET['env'] ) { die($this->db->last_query()); }
        // echo $this->db->last_query();//die;
        $teacher_ids = array();
        $class_ids = array();
        $school_ids = array();
        $grade_ids = array();
        $teachers = '';
        $schools = '';
        $grades = '';
        $history_list = array();
        $hislists = array();
        $grade_user = array();
        $total_count = 0;
        $crm_finish_rate = array();
        $crm_correct_rate = array();
        $kapqinlv = array();
        $finish = array();
        $this->load->model('grades_model');
        $this->load->model('history_model');

        //第一次查询从crm中取 ,然后保存到o2o  , 尽量不要去查crm

        if ($res) {
            //优化
            foreach ($res as $key => $val) {

                $teacher_ids[] = $val->teacher_id;
                $class_ids[] = $val->classes_id;
                $school_ids[] = $val->school_id;
                $grade_ids[] = $val->grade_id;

                $this->load->model('grade_relation_model');
                $relations = $this->grade_relation_model->get_relation_by_grade_id($val->grade_id, 'student');
                $this->load->model('homework_model');
                $student_ids = array();
                if ($relations) {
                    foreach ($relations as $_k => $_v) {
                        $student_ids[] = $_v->item_id;
                    }
                    $grade_auser[$val->grade_id] = count($student_ids);
                }

                $this->load->model('class_relation_model');
                $related_paper = $this->class_relation_model->get_icw_paper_ids($val->classes_id);
                $this->load->model('paper_question_model');
                $related_questions[$val->grade_id][$val->classes_id] = $this->paper_question_model->get_questions_by_paper_ids($related_paper);

                $one_grade = $this->grades_model->get_one($val->grade_id);
                $related_grade = $this->grade_relation_model->get_one_by(array('model' => 'grade', 'item_id' => $val->grade_id));

                if ($related_grade) {
                    $big_grade_id = $related_grade->grade_id;
                    $arr_where = array($big_grade_id, $val->grade_id);
                } else {
                    $arr_where = $val->grade_id;
                }
                if ($one_grade && $one_grade->crm_id > 0 && $val->time > 0) {
                    if (!$crm_data = $this->get_crm_data($val)) {
                        $crm_data = $this->get_crm_data_for_grade($one_grade, $val);
                        $this->insert_crm_data($val, $crm_data);
                    }

                    //建班人数
                    $grade_user_ids = $crm_data['grade_user_ids'];
                    //扫码人数(报班)
                    $check_user_ids = $crm_data['check_user_ids'];
                    //扫码人数(传班)
                    $check_users_ids = $crm_data['check_users_ids'];
                    //实到人数
                    $actual_user_ids = $crm_data['actual_user_ids'];
                    //试听人数
                    $listen_user_ids = $crm_data['listen_user_ids'];
                    //试听成功人数
                    $listen_success_user_ids = $crm_data['listen_success_user_ids'];

                    $grade_user_count[$val->grade_id][$val->classes_id] = $grade_user_ids ? count($grade_user_ids) : 0;
                    $check_user_count[$val->grade_id][$val->classes_id] = $check_user_ids ? count($check_user_ids) : 0;
                    $check_user_counts[$val->grade_id][$val->classes_id] = $check_users_ids ? count($check_users_ids) : 0;
                    $actual_user_count[$val->grade_id][$val->classes_id] = $actual_user_ids ? $actual_user_ids : 0;
                    $listen_user_count[$val->grade_id][$val->classes_id] = $listen_user_ids ? count($listen_user_ids) : 0;
                    $listen_success_user_count[$val->grade_id][$val->classes_id] = $listen_success_user_ids ? count($listen_success_user_ids) : 0;

                    $crm_tmp_hislists = $this->history_model->get_by_class_ids_type_for_classwork($val->classes_id, $check_user_ids, 7, $arr_where, $val->startdate);
                    $history_close_count_score = 0;
                    $crm_closeuser = 0;
                    $new_crm = array();
                    $historyIcloseIdsForCrm = array();
                    foreach ($crm_tmp_hislists as $_key => $_val) {
                        $total_count = count($related_questions[$val->grade_id][$val->classes_id]);

                        $historyIcloseIdsForCrm[] = $_val->id;
                        $history_close_count_score += $_val->new_score;
                        $history_close_count_correct += $_val->new_corrects;
                        $history_close_count_questions += $_val->new_questions;
                        // }
                        $history_close_count_for_crm = count($historyIcloseIdsForCrm);//已完成人数,前n次考勤中出现过的学员
                        $crm_finish_rate[$val->grade_id][$val->classes_id] = round(($history_close_count_for_crm / count($fenmuinfo)) * 100, 2);
                        if ($history_close_count_for_crm > 0) {
                            $crm_correct_rate[$val->grade_id][$val->classes_id] = round(($history_close_count_correct / $history_close_count_questions) * 100, 2);
                        } else {
                            $crm_correct_rate[$val->grade_id][$val->classes_id] = 0;
                        }

                        //作业完成人数
                        $finish_user[$val->grade_id][$val->classes_id] = $history_close_count_for_crm ? $history_close_count_for_crm : 0;
                        //作业完成率（各科/考勤学员*题目数）
                        $finish_rate[$val->grade_id][$val->classes_id] = $history_close_count_for_crm ? round(($history_close_count_for_crm / ($total_count * $check_user_count[$val->grade_id][$val->classes_id])) * 100, 2) : 0;
                        //作业正确率(作业总得分/各科作业题目数*各科作业题目完成人*100)
                        $correct_rate[$val->grade_id][$val->classes_id] = $history_close_count_score ? round(($history_close_count_score / ($total_count * $history_close_count_for_crm * 100)) * 100, 2) : 0;
                    }
                    $kaoqinlv[$val->grade_id][$val->classes_id] = $check_user_count[$val->grade_id][$val->classes_id] ? round(($check_user_count[$val->grade_id][$val->classes_id] / $grade_user_count[$val->grade_id][$val->classes_id]) * 100, 2) : 0;
                }
                $this->load->model('teacher_classes_model');
                $hislists[$val->grade_id][$val->classes_id] = $this->history_model->get_by_class_ids_type_for_classwork($val->classes_id, $student_ids, 7, $val->grade_id, $val->startdate);

            }

            $this->load->model('teacher_model');
            $this->load->model('classes_model');
            $this->load->model('school_model');
            $teachers = $this->teacher_model->get_teachers($teacher_ids);
            $schools = $this->school_model->get_by_ids('', $school_ids);
            $grades = $this->grades_model->get_by_ids_for_teach_info('', $grade_ids);
            $tmp_classes = $this->classes_model->get_by_ids('', $class_ids);
            $classes = array();
            foreach ($tmp_classes as $key => $val) {
                $classes[$val->id] = $val;
            }
        }

        // print_r($classes);
        $data = array(
            'history_list' => $hislists,
            'teachers' => $teachers,
            'schools' => $schools,
            'grades' => $grades,
            'classes' => $classes,
            'lists' => $res,
            'grade_user' => $grade_user,
            'finish_user' => $finish_user,
            'grade_user_count' => $grade_user_count,
            'check_user_count' => $check_user_count,
            'actual_user_count' => $actual_user_count,
            'finish_rate' => $finish_rate,
            'correct_rate' => $correct_rate,
            'kaoqinlv' => $kaoqinlv
        );
        return $data;
    }

    /**
     * @param $item
     * @return array|mixed
     */
    protected function get_crm_data($item)
    {
        $sql = "select `crm_data` from `rh_classwork` where `status` = 99 and `school_id` = {$item->school_id} and `grade_id` = {$item->grade_id} and `classes_id` = {$item->classes_id} limit 1 ;";
        $o2o_data = $this->db->query($sql)->result();

        if (empty($o2o_data)) {
            return array();
        }

        return unserialize($o2o_data[0]->crm_data);
    }

    /**
     * @param $item
     * @param $data
     * @return mixed
     */
    protected function insert_crm_data($item,$data)
    {
        $time_now = time();
        $data = serialize($data);
        $this->db->query("insert into `rh_classwork` (`school_id`,`grade_id`,`classes_id`,`crm_data`,`create`) values ({$item->school_id},{$item->grade_id},{$item->classes_id}, '{$data}', $time_now);");
        return $this->db->insert_id();
    }


    public function get_questionnaire_lists($where, $order = 'id desc', $limit = 0, $offset = 0, $student_ids = '')
    {
        $this->db->select('teacher_id,classes_id,area_id,school_id,subject_id,created,catalog_id,grade_id,chapter_id,startdate,time,homedate,crm_afterclass_complete_rate,crm_afterclass_correct_rate,crm_check_rate');
        if ($where) $this->db->where($where);
        if ($order) $this->db->order_by($order);
        if ($limit && $offset) $this->db->limit($limit, $offset); elseif ($limit) $this->db->limit($limit);
        // $this->db->group_by('classes_id');
        $res = $this->get_by(array('status' => 99));
        $teacher_ids = array();
        $class_ids = array();
        $school_ids = array();
        $grade_ids = array();
        $classes = array();
        $teachers = '';
        $schools = '';
        $grades = '';
        $finish_user = array();
        $evaluate_user_count = array();
        $subjoin_answers = array();
        if ($res) {
            foreach ($res as $key => $val) {
                $relations = array();
                $teacher_ids[] = $val->teacher_id;
                $class_ids[] = $val->classes_id;
                $school_ids[] = $val->school_id;
                $grade_ids[] = $val->grade_id;
                $this->load->model('grade_relation_model');
                $this->load->model('grades_model');
                //查询班级中的学员信息
                $relations = $this->grade_relation_model->get_relation_by_grade_id($val->grade_id, 'student');
                $student_ids = array();
                $this->load->model('history_model');
                if ($relations) {
                    foreach ($relations as $_k => $_v) {
                        $student_ids[] = $_v->item_id;
                    }
                    $grade_user[$val->grade_id] = count($student_ids);
                    $one_grade = $this->grades_model->get_one($val->grade_id);
                    //班级存在并且已经关联CRM
                    if ($one_grade && $one_grade->crm_id > 0 && $val->time > 0) {
                        $crm_data = $this->get_crm_data_for_grade($one_grade, $val);

                        // print_r($crm_data);die;
                        //建班人员
                        $grade_user_ids = $crm_data['grade_user_ids'];
                        //扫码人员
                        $check_user_ids = $crm_data['check_user_ids'];
                        //实到人员
                        $actual_user_ids = $crm_data['actual_user_ids'];
                        if ($check_user_ids) {
                            $hislist = $this->history_model->get_lists_for_admin($val->classes_id, $check_user_ids, 6, $val->grade_id, $val->startdate);
                            //作业完成人数
                            $finish_user[$val->grade_id][$val->classes_id] = $hislist['total_his_user'];

                        } else {
                            $finish_user[$val->grade_id][$val->classes_id] = 0;
                        }
                        $grade_user_count[$val->grade_id][$val->classes_id] = $grade_user_ids ? count($grade_user_ids) : 0;
                        $check_user_count[$val->grade_id][$val->classes_id] = $check_user_ids ? count($check_user_ids) : 0;
                        $actual_user_count[$val->grade_id][$val->classes_id] = $actual_user_ids ? $actual_user_ids : 0;
                        // 作业完成率
                        //$finish_rate[$val->grade_id][$val->classes_id] = $hislist['answered'] ? round(($hislist['answered']/$hislist['total_qids'])*100,2) : 0;
                        //作业正确率
                        //$correct_rate[$val->grade_id][$val->classes_id] = $hislist['total_score'] ? round(($hislist['total_score']/($hislist['total_his_user']*100))*100,2) : 0;

                        //$kaoqinlv[$val->grade_id][$val->classes_id] = $check_user_count[$val->grade_id][$val->classes_id] ? round(($check_user_count[$val->grade_id][$val->classes_id]/$grade_user_count[$val->grade_id][$val->classes_id])*100,2) : 0;
                        //5分评价
                        /*
                         * 1.CRM判断老师类型，主讲老师，面授老师，辅导老师
                         * 2.获取老师的评价信息 a.主讲老师(面授+直播1) b.面授老师(直播卷第1题) c.辅导老师(直播卷第2题)
                         * 3.取出5星评价的数量和总的评价数量
                         */
                        $level5_info = $this->get_level5_by_teacher($val->teacher_id, $one_grade, $val->classes_id);
                        $level5[$val->grade_id][$val->classes_id] = $level5_info['level5'];
                        $js_type[$val->grade_id][$val->classes_id] = $level5_info['js_type'];
                        $answer_detail[$val->grade_id][$val->classes_id] = $level5_info['answer_detail'];
                        //评价的学员总数
                        //评价的扫码学员人数
                        $this->load->model('questionnaire_student_model');
                        $evaluate_users = 0;
                        if ($check_user_ids) {
                            $evaluate_users = $this->questionnaire_student_model->get_evaluate_users($val->classes_id, $check_user_ids, $val->grade_id);
                        }
                        $evaluate_user_count[$val->grade_id][$val->classes_id] = $evaluate_users ? count($evaluate_users) : 0;
                        //评价学员数
                        //$all_evaluate_users = $this->questionnaire_student_model->get_evaluate_users($val->classes_id, false, $val->grade_id);
                        //$all_evaluate_user_count[$val->grade_id][$val->classes_id] = $all_evaluate_users ? count($all_evaluate_users) : 0;
                        //附加问题
                        $subjoin_answers[$val->grade_id][$val->classes_id] = $this->questionnaire_student_model->get_subjoin_answers($val->teacher_id, $val->grade_id, $val->classes_id);
                        // $this->load->model('teacher_classes_model');
                        // $hislists[$val->grade_id][$val->classes_id] = $this->history_model->get_by_class_ids_type($val->classes_id,$student_ids,10,$val->grade_id);

                    }
                }
            }

            $this->load->model('teacher_model');
            $this->load->model('classes_model');
            $this->load->model('school_model');
            $teachers = $this->teacher_model->get_teachers($teacher_ids);
            $schools = $this->school_model->get_by_ids('', $school_ids);
            $grades = $this->grades_model->get_by_ids_for_teach_info('', $grade_ids);
            $tmp_classes = $this->classes_model->get_by_ids('', $class_ids);
            $classes = array();
            foreach ($tmp_classes as $key => $val) {
                $classes[$val->id] = $val;
            }
        }
        $data = array(
            'teachers' => $teachers,
            'schools' => $schools,
            'grades' => $grades,
            'lists' => $res,
            'classes' => $classes,
            'finish_user' => $finish_user,
            'evaluate_user_count' => $evaluate_user_count, //扫码评价学员数
            // 'all_evaluate_user_count'   => $all_evaluate_user_count,
            'subjoin_answers' => $subjoin_answers, //学员评价
            'level5' => $level5, //5星评价数
            'js_type' => $js_type,
            'grade_user_count' => $grade_user_count,
            'check_user_count' => $check_user_count,
            'actual_user_count' => $actual_user_count,
            'answer_detail' => $answer_detail,
        );
        return $data;
    }


    public function has_permission($user_id, $class_id, $grade_id)
    {
        $row = $this->get_one_by(array('classes_id' => $class_id, 'grade_id' => $grade_id));
        $sort_id = $row->sort;
        $class_ids = $this->get_class_ids(array('sort < ' => $sort_id, 'grade_id' => $grade_id));
        if (!$class_ids) return true;
        $this->load->model('user_classes_relation_model');
        $this->user_classes_relation_model->db->where_in('class_id', $class_ids);
        $related = $this->user_classes_relation_model->get_by(array('user_id' => $user_id, 'grade_id' => $grade_id));
        $count = 0;
        if ($related) {
            foreach ($related as $key => $val) {
                if ($val->is_offline == 0) {
                    if ($val->practise_finish == 100 && $val->video_finish == 100) {
                        ++$count;
                    }
                } else {
                    if ($val->practise_finish == 100) {
                        ++$count;
                    }
                }
            }
            if ($count != count($class_ids)) {
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }

    }

    /**
     * 获取老师的5星评价
     * @param $teacher_id
     * @param $grade
     * @param $class_id
     * @return boolean
     */
    public function get_level5_by_teacher($teacher_id, $grade, $class_id)
    {
        $js_types = array(
            '1518' => '面授',
            '1519' => '辅导',
            '1520' => '主讲'
        );
        $crmDB = $this->load->database('crm', true);
        $sql = "SELECT * FROM CLASS_MANAGE WHERE OTM_CLASS_ID = {$grade->crm_id}";
        $class_manage = $crmDB->query($sql)->result();
        if ($class_manage) {
            $class_manage = $class_manage[0];
        } else {
            return false;
        }

        //获取该老师的评价数据
        $this->load->model('questionnaire_student_model');
        $level5 = $this->questionnaire_student_model
            ->get_level5_answers($teacher_id, $class_manage->JS_TYPE, $grade->id, $class_id);
        $js_type = $js_types[$class_manage->JS_TYPE];
        $answer_detail = $this->questionnaire_student_model
            ->get_answer_detail($teacher_id, $class_manage->JS_TYPE, $grade->id, $class_id);
        return compact('level5', 'js_type', 'answer_detail');
    }

    //获取教师手机号
    public function get_teacher_mobile($teacher_id)
    {
        $sql = "SELECT mobile FROM `rh_teachers` WHERE id = '$teacher_id'";
        $result = $this->db->query($sql)->result();
        return $result[0]->mobile;
    }

    //保存上传图片信息
    public function insert_image_info($data_arr)
    {
        $sql = "INSERT INTO rh_teacher_classes_images (
                    teacher_classes_id,
                    teacher_mobile,
                    image_path,
                    image_from,
                    create_time
                )
                VALUES(
                    '" . $data_arr['teacher_classes_id'] . "',
                    '" . $data_arr['teacher_mobile'] . "',
                    '" . $data_arr['image_path'] . "',
                    '" . $data_arr['image_from'] . "',
                    '" . $data_arr['create_time'] . "'
                )";
        return $this->db->simple_query($sql);
    }

    //查看当前教师是否存在图片
    public function get_teacher_classes_image($teacher_classes_id)
    {
        $sql = "SELECT count(*) AS num FROM `rh_teacher_classes_images` WHERE teacher_classes_id = '$teacher_classes_id' AND image_from = 0";
        $result = $this->db->query($sql)->result();
        return $result[0]->num;
    }

    public function get_teacher_classes_image_list($teacher_classes_id)
    {
        $sql = "SELECT * FROM `rh_teacher_classes_images` WHERE teacher_classes_id = '$teacher_classes_id' AND image_from = 0";
        return $this->db->query($sql)->result();
    }

    //获取班级正式学员
    public function get_normal_student($grade_id)
    {

        $this->load->model('grades_model');
        $grade = $this->grades_model->get_one_by(array('id' => $grade_id));

        $crmDB = $this->load->database('crm', true);

        $sql = "SELECT DISTINCT
            STUD.STU_PHONE,STUD.STU_NAME
        FROM
                CLASS_SCHEDULE CLSC
                ,STUDENT_CLASS STCL
                ,STUDENT STUD
                ,CLASS_MANAGE CLMA
                ,COURSE COR
                ,CLASS_SCHEDULE CLSC2
                ,CLASS_SCHEDULE_STUDENT CSST
                ,CLASS_SCHEDULE_STUDENT CSST2
        WHERE
                CLSC.CLASS_ID = {$grade->crm_id}            
        AND CLSC.CLASS_ID = STCL.CLASS_ID
        AND (TO_DATE(TO_CHAR(CLSC.ATTEND_DATE,'yyyy-MM-dd'),'yyyy-MM-dd') >= 
			TO_DATE (TO_CHAR(STCL.CREATE_DATE,'yyyy-MM-dd'),'yyyy-MM-dd') 
			OR TO_DATE(TO_CHAR(CLSC.ATTEND_DATE,'yyyy-MM-dd'),'yyyy-MM-dd') = 
			TO_DATE (TO_CHAR(CSST.CREATE_DATE,'yyyy-MM-dd'),'yyyy-MM-dd'))
        AND STCL.SC_TYPE = 1244
        AND STCL.STU_ID = STUD.STUDENT_ID
        AND CLSC.CLASS_ID=CLMA.OTM_CLASS_ID
        AND CLMA.COURSE_ID=COR.COURSE_ID
        AND CLSC.CLASS_ID=CLSC2.CLASS_ID AND CLSC2.ABOUT<=ceil(COR.KC * 0.3 + 1)
        AND CLSC2.C_S_ID=CSST.CS_ID
        AND STUD.STUDENT_ID=CSST.STU_ID
        AND CLSC.C_S_ID=CSST2.CS_ID AND STUD.STUDENT_ID=CSST2.STU_ID 
		AND CSST.IS_OTOPP IS NULL";

        $result = $crmDB->query($sql)->result();

        $normal_student = array();

        foreach ($result as $val) {
            $normal_student[$val->STU_PHONE] = $val->STU_NAME;
        }

        return $normal_student;

    }

    /**
     * 更新开课时间与家庭作业完成时间
     * @param $id
     * @param $start_time
     * @return mixed
     */
    public function update_work_time($id, $start_time)
    {
        $time_group = array(
            'startdate' => $start_time,
            'homedate' => $start_time + self::HOME_WORK_INTERVAL
        );
        $this->db->where(array('id' => $id));
        return $this->db->update($this->table, $time_group);
    }



}