<?php

	/****************************
		设置自动出价
	****************************/
	public function setAuto()
	{
		$memberid = $this->checkPCLogin();
		if(!$memberid)	//若未登录
		{
			echo json_encode(array('msg' => '您尚未登录！', 'tag' => false));exit;
		}
		if(IS_AJAX)
		{
			$price = I("post.price", 0, 'int');	//价格
			$type  = I("post.type", 0, 'int');	//出价类型 1 跳价 2 代理
			$productid  = I("post.productid", 0, 'int');	//产品id
			if($price && $type && $productid)
			{
				//判断代理价格是否最高
				
				//写入DB
				$proxy = M('jp_proxyrecords')->where('ProductID=' . $productid . ' and MemberID=' . $memberid)->find();
				if(!empty($proxy))	//您已设置
				{
					M('jp_proxyrecords')->where('ProxyID=' . $proxy['ProxyID'])->save(array('ProType' => $type, 'Price' => $price));
					$lastid = $proxy['ProxyID'];
				}
				else	//您未设置
				{
					$lastid = M('jp_proxyrecords')->data(array('Price' => $price, 'ProductID' => $productid, 'MemberID' => $memberid, 'ProType' => $type))->add();
				}
				//写入MC
				
				$proxy = M('jp_proxyrecords')->where('ProxyID=' . $lastid)->find();
				if(!empty($proxy))
				{
					echo json_encode(array('data' => $proxy, 'tag' => true));exit;
				}
			}
			echo json_encode(array('msg' => '设置失败！', 'tag' => false));exit;
		}
	}
	
	
	
	
	/********* dfasfsadflj **********/
	
	
	
	
	/********************
		拍品详细页
	********************/
    public function detail(){
    	//更新点击次数（如果该场次尚未结束）
    	$ProductID=\Input::getVar(I('get.ProductID',0));
    	$listOne=D('Content/JpProduct')->find($ProductID);
    	$listOne['BigImgs']=explode('$',$listOne['BigImgs']);
    	$listOne['SmallImgs']=explode('$',$listOne['SmallImgs']);
    	
		//我的自动出价记录
		$memberID = $this->checkPCLoginAjax();
		if($memberID)
		{
			$proxy = M('jp_proxyrecords')->where("MemberID=" . $memberID . " and ProductID=" . $ProductID)->find();
			$this->assign('proxy', $proxy);
		}
		
		//已有n人设置代理
		$proxycount = M('jp_proxyrecords')->where("ProductID=" . $ProductID . " and ProType=2")->count();
		$this->assign('proxycount', $proxycount);
		
		//出价记录
		$res = $this->ItemMemGet($ProductID);
		if(!empty($res))	//若正在拍，从MC读取
		{
			$arr = $this->getMemData('jp_records');
			//$arr = array_reverse($arr);
			$records = array();
			for($i = count($arr) - 1; $i >= 0 && $i >= count($arr) - 6; $i--)
			{
				$member = M("jp_memberinfo")->where('MemberID=' . $arr[$i]['MemberID'])->find();
				$arr[$i]['MemberName'] = $member['MemberName'];
				$records[] = $arr[$i];
			}
		}
		else	//若未拍、已拍完，从DB读取
		{
			$records = M("jp_records")->join("left join jbr_jp_memberinfo on jbr_jp_records.MemberID = jbr_jp_memberinfo.MemberID")->where("ProductID=" . $ProductID)->limit(5)->order("RecordTime desc")->getField("Price,RecordTime,MemberName");
			//print_r($records);exit;
		}//
		$this->assign('records', $records);
		
    	//点击次数计算
    	$this->doDjCount($listOne);
    	//竞价预告
		$this->getPredi($listOne,4);
    	$this->assign('listOne',$listOne);
    	$this->display();
    }