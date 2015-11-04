<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Modules/Scorm2004/classes/class.ilObjSCORM2004LearningModule.php';
/**
* Class ilSCORM2004TrackingExchange
*
* @author Uwe Kohnle <kohnle@internetlehrer-gmbh.de>
*
* @ingroup ModulesScorm2004
*/
class ilSCORM2004TrackingExchange
{

	function checkIfPatternExists() {
		global $ilDB;
	 	$res = $ilDB->queryF(
			'SELECT obj_id FROM sahs_exchange_pattern WHERE obj_id = %s',
		 	array('integer'),
		 	array($this->object->getId())
		);
		if ($ilDB->numRows($res)) return true;
		return false;
	}
	
	function checkIfWritable() {
		global $ilDB;
	 	$res = $ilDB->queryF(
			'SELECT writable FROM sahs_exchange_pattern WHERE obj_id = %s',
		 	array('integer'),
		 	array($this->object->getId())
		);
		$val_rec = $ilDB->fetchAssoc($res);
		if ($val_rec["writable"] == 1) return true;
		return false;
	}
	
	function checkIfLog() {
		global $ilDB;
	 	$res = $ilDB->queryF(
			'SELECT count(*) as counter FROM sahs_exchange_log WHERE obj_id = %s',
		 	array('integer'),
		 	array($this->object->getId())
		);
		$val_rec = $ilDB->fetchAssoc($res);
		if ($val_rec["counter"] > 0) return true;
		return false;
	}
	
	function getSuspend_data($a_obj_id,$a_user_id) {
		global $ilDB,$ilUser;
	 	$res = $ilDB->queryF(
			'SELECT suspend_data FROM cmi_node, cp_node WHERE slm_id= %s AND cmi_node.cp_node_id = cp_node.cp_node_id and user_id= %s',
		 	array('integer','integer'),
		 	array($a_obj_id,$a_user_id)
		);
		if (!$ilDB->numRows($res)) return "";
		$val_rec = $ilDB->fetchAssoc($res);//nur 1 SCO pro LM
		return $val_rec["suspend_data"];
		// if($auto_last_visited) $status['last_visited'] = $val_rec["last_visited"];
		// if ($ilDB->numRows($res)) return true;
		// return false;
	}
	
	function deletePatternAndObjectsData() {
		global $ilDB;
	 	$res = $ilDB->queryF(
			'DELETE FROM sahs_exchange_pattern WHERE obj_id = %s',
		 	array('integer'),
		 	array($this->object->getId())
		);
	 	$res = $ilDB->queryF(
			'DELETE FROM sahs_exchange_object WHERE target_obj_id = %s',
		 	array('integer'),
		 	array($this->object->getId())
		);
	}
	
	function getLearningModulesWithPattern() {
		global $ilDB;
		$lmTitles = array();
	 	$res = $ilDB->queryF(
			'SELECT sahs_exchange_pattern.obj_id, title FROM sahs_exchange_pattern, il_meta_general WHERE sahs_exchange_pattern.obj_id <> %s AND sahs_exchange_pattern.obj_id=il_meta_general.obj_id',
		 	array('integer'),
		 	array($this->object->getId())
		);
		while($row = $ilDB->fetchAssoc($res))
		{
			$lmTitles[$row['obj_id']] = $row['title'];
		}
		return $lmTitles;
	}
	
	function getSelectedExchangeObjects($a_obj_id) {
		global $ilDB;
		$lmEx = array();
	 	$res = $ilDB->queryF(
			'SELECT * FROM sahs_exchange_object WHERE target_obj_id = %s',
		 	array('integer'),
		 	array($a_obj_id)
		);
		while($row = $ilDB->fetchAssoc($res))
		{
			// $lmEx[$row['target_obj_id'].'.'.$row['target_sco_id'].'.'.$row['target_field_id']] = $row['source_obj_id'].'.'.$row['source_sco_id'].'.'.$row['source_field_id'];
			$lmEx[$row['target_obj_id'].'.'.$row['target_field_id']] = $row['source_obj_id'].'.'.$row['source_field_id'];
		}
		return $lmEx;
	}
	
	function getPossibleExchangeObjects($a_obj_id_ar) {
		global $ilDB;
		$lmTitles = array();
		$lmPattern = array();
		$return_ar=array();
		$s_obj_id=implode(',',$a_obj_id_ar);
		if ($s_obj_id!="") {
			$res = $ilDB->queryF(
				'SELECT sahs_exchange_pattern.obj_id, title, sahs_exchange_pattern.pattern FROM sahs_exchange_pattern, il_meta_general '
				.'WHERE sahs_exchange_pattern.obj_id <> %s AND sahs_exchange_pattern.obj_id in ('.$s_obj_id.') AND sahs_exchange_pattern.obj_id=il_meta_general.obj_id',
				array('integer'),
				array($this->object->getId())
			);
			while($row = $ilDB->fetchAssoc($res))
			{
				$lmTitles[$row['obj_id']] = $row['title'];
				$lmPattern[$row['obj_id']] = $row['pattern'];
			}
			foreach($lmPattern as $lm => $pattern) {
				$tmp=self::getPatternEssentials($pattern);
				for ($i=0; $i<$tmp["p2_i_counter_fields"];$i++) {
					$pos=$tmp["p2_pos"][$i];
					$return_ar[$lm.'.'.$pos]=$lmTitles[$lm].': '.$tmp["p2_c".$pos];
				}
			}
		}
		return $return_ar;
	}
	
	function exchangeTrackingSelectSave($ar_save) {
		global $ilDB;
	 	$res = $ilDB->queryF(
			'DELETE FROM sahs_exchange_object WHERE target_obj_id = %s',
		 	array('integer'),
		 	array($this->object->getId())
		);
		foreach ($ar_save as $key => $value) {
			$source = explode('.',$value);
			$source_obj_id = $source[0];
			$source_field_id = $source[1];
			$ilDB->insert('sahs_exchange_object', 
				array(
					'target_obj_id'		=> array('integer', $this->object->getId()),
					'target_sco_id'		=> array('integer', 0),
					'target_field_id'	=> array('integer', $key),
					'source_obj_id'		=> array('integer', $source_obj_id),
					'source_sco_id'		=> array('integer', 0),
					'source_field_id'	=> array('integer', $source_field_id)
					// 'c_timestamp'	=> array('timestamp', ilUtil::now())
				)
			);
		}
	}
	
	function getPatternAr($a_obj_id) {
		global $ilDB;
		$a_pattern = array();
		$a_pattern["obj_id"] = 0;
		$a_pattern["writable"] = 0;
		$a_pattern["pattern"] = "";
		$a_pattern["fields"] = 0;
		$a_pattern["active"] = 0;
		$a_pattern["c_timestamp"] = "";
		
	 	$res = $ilDB->queryF(
			'SELECT * FROM sahs_exchange_pattern WHERE obj_id = %s',
		 	array('integer'),
		 	array($a_obj_id)
		);
		if (!$ilDB->numRows($res)) return $a_pattern;
		$val_rec = $ilDB->fetchAssoc($res);//nur 1 SCO pro LM
		return $val_rec;
	}

	function patternCreate($b_useSuspend, $i_scoId, $s_pattern) {
		global $ilDB,$ilUser;
		if ($b_useSuspend == true) $s_pattern=self::getSuspend_data($this->object->getId(),$ilUser->getId());
		$pattern_ar=self::getPatternEssentials($s_pattern);
		$i_fields=$pattern_ar["p2_i_counter_fields"];
		$i_writable = 0;
		// if ($b_writable == true) $i_writable = 1;
		
	 	$res = $ilDB->queryF(
			'SELECT sco_id FROM sahs_exchange_pattern WHERE obj_id= %s AND sco_id = %s',
		 	array('integer','integer'),
		 	array($this->object->getId(),$i_scoId)
		);
		if (!$ilDB->numRows($res)) {
			$ilDB->insert('sahs_exchange_pattern', 
				array(
					'obj_id'		=> array('integer', $this->object->getId()),
					'sco_id'		=> array('integer', $i_scoId),
					'pattern'		=> array('text', $s_pattern),
					'fields'		=> array('integer', $i_fields),
					'writable'		=> array('integer', $i_writable),
					'c_timestamp'	=> array('timestamp', ilUtil::now())
				)
			);
		} else {
			$ilDB->update('sahs_exchange_pattern',
				array(
					'pattern'		=> array('text', $s_pattern),
					'fields'		=> array('integer', $i_fields),
					'c_timestamp'	=> array('timestamp', ilUtil::now())
				),
				array(
					'obj_id'		=> array('integer', $this->object->getId()),
					'sco_id'		=> array('integer', $i_scoId)
				)
			);
		}
		return true;
	}

	function patternPropertiesUpdate($i_scoId, $b_writable) {
		global $ilDB,$ilUser;
		$i_writable = 0;
		if ($b_writable == true) $i_writable = 1;
		
		$ilDB->update('sahs_exchange_pattern',
			array(
				'writable'		=> array('integer', $i_writable)				),
			array(
				'obj_id'		=> array('integer', $this->object->getId()),
				'sco_id'		=> array('integer', $i_scoId)
			)
		);
		if ($i_writable == 0) {
			$res = $ilDB->queryF(
				'DELETE FROM sahs_exchange_object WHERE target_obj_id = %s',
				array('integer'),
				array($this->object->getId())
			);
		}
		return true;
	}
	
	function getLogPatterns() {
		global $ilDB;
		$patterns = array();
	 	$res = $ilDB->queryF(
			'SELECT pattern, pattern_created FROM sahs_exchange_log WHERE obj_id = %s GROUP BY pattern_created, pattern ORDER BY pattern_created DESC',
		 	array('integer'),
		 	array($this->object->getId())
		);
		while($row = $ilDB->fetchAssoc($res))
		{
			$patterns[] = array($row['pattern_created'],$row['pattern']);
		}
		return $patterns;
	}
	function getLogSuspendData($pattern_created) {
		global $ilDB,$ilUser;
		$suspends = array();
	 	$res = $ilDB->queryF(
			'SELECT user_id, suspend_data, c_timestamp, failure FROM sahs_exchange_log WHERE obj_id = %s AND pattern_created = %s ORDER BY c_timestamp DESC',
		 	array('integer','timestamp'),
		 	array($this->object->getId(),$pattern_created)
		);
		while($row = $ilDB->fetchAssoc($res))
		{
			$s_user = "";
			$user = $row['user_id'];
			if(ilObject::_exists($user)  && ilObject::_lookUpType($user) == 'usr') {
				$e_user = new ilObjUser($user);
				$s_user = $e_user->getLastname() . ', ' . $e_user->getFirstname();
			}
			$suspends[] = array($user,$s_user,$row['c_timestamp'],$row['failure'],$row['suspend_data']);
		}
		return $suspends;
	}

	function changeLogFailureEntry($user_id,$c_timestamp,$failure) {
		global $ilDB;
		$ilDB->update('sahs_exchange_log',
			array(
				'failure'		=> array('integer', $failure)				),
			array(
				'obj_id'		=> array('integer', $this->object->getId()),
				'user_id'		=> array('integer', $user_id),
				'c_timestamp'	=> array('timestamp', $c_timestamp)
			)
		);
	}

	
	function n64() {
		$n64="0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_$";
		return $n64;
	}
	function translateToArticulate3($i2a) {
		if ($i2a < 64) return self::n64()[$i2a];
		else {
			return "~2" . (self::n64()[$i2a%64]) . (self::n64()[$i2a/64]);
		}
	}
	function translateFromArticulate3($sa) {
		if ($sa[0]=="~") {
			return (strpos(self::n64(), substr($sa,3,1)) * 64) + strpos(self::n64(), substr($sa,2,1));
		} else {
			return strpos(self::n64(), substr($sa,0,1));
		}
	}
	function translateFromArticulateWithoutTilde($sa) {
		if (strlen($sa) == 3) $sa = substr($sa,1);//?
		if (strlen($sa) == 2) return (strpos(self::n64(), substr($sa,1,1)) * 64) + strpos(self::n64(), substr($sa,0,1));
		return strpos(self::n64(), substr($sa,0,1));
	}
	function translateToArticulateWithoutTilde($i2a) {
		if ($i2a < 64) return self::n64()[$i2a];
		else {
			return "" . (self::n64()[$i2a%64]) . (self::n64()[$i2a/64]);
		}
	}

	function getContentAr($p2) {
		$field = array();
		$startPos = 0;
		$p2Length = mb_strlen($p2,'UTF-8');
		for($i=0; $i<$p2Length; $i++) {
			if($startPos >= $p2Length) break;
			if(substr($p2,$startPos,1) == '~') {
				$lengthAtPos=self::translateFromArticulate3(mb_substr($p2,$startPos,4,'UTF-8'));
				$field[$i] = mb_substr($p2,$startPos+4,$lengthAtPos,'UTF-8');
				$startPos = $startPos + 4 + $lengthAtPos;
			} else {
				$lengthAtPos=self::translateFromArticulate3(mb_substr($p2,$startPos,1,'UTF-8'));
				$field[$i] = mb_substr($p2,$startPos+1,$lengthAtPos,'UTF-8');
				$startPos = $startPos + 1 + $lengthAtPos;
			}
		}
		return $field;
	}

	function makeContentStringOfContentAr($a_c) {
		$s_return = "";
		for ($i=0; $i<count($a_c); $i++) {
			$s_return .= self::translateToArticulate3(mb_strlen($a_c[$i],'UTF-8')) . $a_c[$i];
		}
		return $s_return;
	}

	function getPatternEssentials($s_pattern) {
		$counter_s = array();
		$counter_i = array();
		$field = array();
		$startPos = 3;
		for($i=0;$i<7;$i++) {
			if(substr($s_pattern,$startPos,1) == '~') {
				$counter_s[$i] = substr($s_pattern,$startPos,4);
				$counter_i[$i]=self::translateFromArticulate3($counter_s[$i]);
				$field[$i] = mb_substr($s_pattern,$startPos+4,$counter_i[$i],'UTF-8');
				// echo '<br>'.$counter_s[$i].$field[$i];
				$startPos = $startPos + 4 + $counter_i[$i];
			} else {
				$counter_s[$i] = substr($s_pattern,$startPos,1);
				$counter_i[$i]=self::translateFromArticulate3($counter_s[$i]);
				$field[$i] = mb_substr($s_pattern,$startPos+1,$counter_i[$i],'UTF-8');
				// echo '<br>'.$counter_s[$i].$field[$i];
				$startPos = $startPos + 1 + $counter_i[$i];
			}
		}
		// $pattern_ar[0] = "" . substr($s_pattern,0,3) . $counter_s[0] . $field[0] . $counter_s[1] . $field[1];
		// $pattern_ar[1] = "" . $counter_s[2] . $field[2] . $counter_s[3] . $field[3];
		// $pattern_ar[2] = "" . $counter_s[4] . $field[4] . $counter_s[5] . $field[5] . $counter_s[6] . $field[6];
		// if (implode($pattern_ar) == $s_pattern) echo 'PASST';
		// else echo '<hr>'.$s_pattern.'<br>'.implode($pattern_ar).'<hr>';


		// p0 no more needed
		// $p1 = $pattern_ar[0];
		// $p2 = $pattern_ar[1];
		// $suspend["p1_init"] = "2";
		// $suspend["p1_s_counter_org"] = substr($p1,1,2);
		// $suspend["p1_i_counter_org"] = self::translateFromArticulateWithoutTilde( $suspend["p1_s_counter_org"] );
		// $suspend["p1_content"] = substr($p1,3);
		// $suspend["p2_init"] = "~2";
		// $suspend["p2_s_counter_org"] = substr($p2,2,2);
		// $suspend["p2_i_counter_org"] = self::translateFromArticulateWithoutTilde( $suspend["p2_s_counter_org"] );
		// $p2c = substr($p2,4,mb_strlen($p2,'UTF-8')-5);
		// $suspend["p2_content_org"] = substr($p2,4,(mb_strlen($p2,'UTF-8')-5));//$p2c;
		// $suspend["p2_end"] = "0";
		// $suspend["p3"] = $pattern_ar[2];

		$suspend["p1_init"] = "2";
		$suspend["p1_s_counter_org"] = substr($s_pattern,1,2);
		$suspend["p1_i_counter_org"] = self::translateFromArticulateWithoutTilde( $suspend["p1_s_counter_org"] );
		$suspend["p1_content"] = $counter_s[0] . $field[0] . $counter_s[1] . $field[1];
		$suspend["p2_s_counter_org"] = $counter_s[2];
		$suspend["p2_i_counter_org"] = $counter_i[2];//self::translateFromArticulateWithoutTilde( $suspend["p2_s_counter_org"] );
		$p2c = "" . $field[2];
		$suspend["p2_content_org"] = "" . $field[2];
		$suspend["p3"] = "" . $counter_s[3] . $field[3] . $counter_s[4] . $field[4] . $counter_s[5] . $field[5] . $counter_s[6] . $field[6];

		$startPos = 0;
		$suspend["p2_pos"] = array();
		$suspend["p2_empty"] = array();
		$p2cLength = mb_strlen($p2c,'UTF-8');
		for($i=0; $i<$p2cLength; $i++) {
			if($startPos >= $p2cLength) break;
			if(substr($p2c,$startPos,1) == '~') {
				$lengthAtPos=self::translateFromArticulate3(substr($p2c,$startPos,4));
				$suspend["p2_empty"][$i] = mb_substr($p2c,$startPos+4,$lengthAtPos,'UTF-8');
				$startPos = $startPos + 4 + $lengthAtPos;
			} else {
				$lengthAtPos=self::translateFromArticulate3(substr($p2c,$startPos,1));
				$field = mb_substr($p2c,$startPos+1,$lengthAtPos,'UTF-8');
				if ($lengthAtPos==3 && preg_match('/#\d\d/',$field) != false) {
					$suspend["p2_empty"][$i] = '^';
					$suspend["p2_pos"][] = $i;
					$suspend["p2_c".$i] = $field;
				} else {
					$suspend["p2_empty"][$i] = $field;
				}
				$startPos = $startPos + 1 + $lengthAtPos;
			}
		}
	
		// $suspend["p2_content_empty"] = preg_replace('/3#\d\d/','1^',$suspend["p2_content_org"]);
		$suspend["p2_content_empty"] = self::makeContentStringOfContentAr($suspend["p2_empty"]);
		// $suspend["p2_i_counter_empty"] = mb_strlen($suspend["p2_content_empty"],'UTF-8');
		// $suspend["p2_s_counter_empty"] = self::translateToArticulateWithoutTilde( $suspend["p2_i_counter_empty"] );
		// $suspend["p1_i_counter_empty"] = $suspend["p1_i_counter_org"] - mb_strlen($suspend["p2_content_org"],'UTF-8') + mb_strlen($suspend["p2_content_empty"],'UTF-8');
		// $suspend["p1_s_counter_empty"] = self::translateToArticulateWithoutTilde( $suspend["p1_i_counter_empty"] );
		$suspend["p2_i_counter_fields"] = count($suspend["p2_pos"]);
		return $suspend;
	}
	
	function createNewSuspend($suspend,$p2_content_new_ar) {
		$p2_content_new = self::makeContentStringOfContentAr($p2_content_new_ar);
		$p2_s_counter_new = self::translateToArticulate3( mb_strlen($p2_content_new,'UTF-8') );

		$s_return = "";
		$s_return .= $suspend["p1_content"];
		$s_return .= $p2_s_counter_new;
		$s_return .= $p2_content_new;
		$s_return .= $suspend["p3"];

		$p1_s_counter_new = self::translateToArticulateWithoutTilde( mb_strlen($s_return,'UTF-8') );
		
		$s_return = $suspend["p1_init"] . $p1_s_counter_new . $s_return;
		// var_dump($p2_content_new);

		return $s_return; 
	}

	function insertTrackingData4Exchange($objId, $userId, $suspend_data) {
		global $ilDB,$ilUser;
		$res = $ilDB->queryF(
			'SELECT cp_node_id FROM cp_node WHERE slm_id = %s and nodename = %s',
			array('integer','text'),
			array($objId,'item')
		);
		$rowtmp=$ilDB->fetchAssoc($res);
		$cp_node_id=$rowtmp['cp_node_id'];
		$cmi_node_id = $ilDB->nextId('cmi_node');
		$a_data=array(
			'accesscount'			=> array('integer', 1),
			'accessduration'		=> array('text', "0"),
			'accessed'				=> array('text', "0"),
			'activityabsduration'	=> array('text', "0"),
			'activityattemptcount'	=> array('integer', 0),
			'activityexpduration'	=> array('text', "0"),
			'activityprogstatus'	=> array('integer', 0),
			'attemptabsduration'	=> array('text', "0"),
			'attemptcomplamount'	=> array('float', 0),
			'attemptcomplstatus'	=> array('integer', 0),
			'attemptexpduration'	=> array('text', "0"),
			'attemptprogstatus'		=> array('integer', 0),
			'audio_captioning'		=> array('integer', 0),
			'audio_level'			=> array('float', 1),
			'availablechildren'		=> array('text', null),
			'cmi_node_id'			=> array('integer', $cmi_node_id),
			'completion'			=> array('float', 0),
			'completion_status'		=> array('text', "incomplete"),
			'completion_threshold'	=> array('text', null),
			'cp_node_id'			=> array('integer', $cp_node_id),
			'created'				=> array('text', "0"),
			'credit'				=> array('text', "credit"),
			'delivery_speed'		=> array('float', 1),
			'c_entry'				=> array('text', "resume"),
			'c_exit'				=> array('text', "suspend"),
			'c_language'			=> array('text', null),
			'launch_data'			=> array('clob', null),
			'learner_name'			=> array('text', $ilUser->getFirstname()." ".$ilUser->getLastname()),
			'location'				=> array('text', null),
			'c_max'					=> array('float', 100),
			'c_min'					=> array('float', 0),
			'c_mode'				=> array('text', null),
			'modified'				=> array('text', "0"),
			'progress_measure'		=> array('float', null),
			'c_raw'					=> array('float', 0),
			'scaled'				=> array('float', 0),
			'scaled_passing_score'	=> array('float', null),
			'session_time'			=> array('text', "PT0S"),
			'success_status'		=> array('text', "unknown"),
			'suspend_data'			=> array('clob', $suspend_data),
			'total_time'			=> array('text', "PT0S"),
			'user_id'				=> array('integer', $userId),
			'c_timestamp'			=> array('timestamp', date('Y-m-d H:i:s')),
			'additional_tables'		=> array('integer', 0)
		);
		$ilDB->insert('cmi_node', $a_data);

		//only log
		$pattern="";
		$pattern_created="";
		$res = $ilDB->queryF(
			'SELECT pattern, c_timestamp FROM sahs_exchange_pattern WHERE obj_id = %s',
			array('integer'),
			array($objId)
		);
		while($row = $ilDB->fetchAssoc($res))
		{
			$pattern = $row['pattern'];
			$pattern_created = $row['c_timestamp'];
		}
		if ($pattern != "") {
			$ilDB->insert('sahs_exchange_log', 
				array(
					'obj_id'			=> array('integer', $objId),
					'user_id'			=> array('integer', $userId),
					'pattern'			=> array('text', $pattern),
					'pattern_created'	=> array('timestamp', $pattern_created),
					'suspend_data'		=> array('clob', $suspend_data),
					'c_timestamp'		=> array('timestamp', date('Y-m-d H:i:s'))
				)
			);
		}


	}

	public function initTrackingData($userId, $packageId) {
		global $ilDB,$ilUser,$ilLog;
		//check if soemething to do
		$a_t_pattern=self::getPatternAr($packageId);
		if ($a_t_pattern["writable"] == 0) return;
		// $ilLog->write("DEBUG SQL".$row);

		//check fields
		$a_ex=self::getSelectedExchangeObjects($packageId);
		if(count($a_ex)==0) return;
		
		//check if data available
		$t_suspend = self::getSuspend_data($packageId,$userId);
		if ($t_suspend != "") return;
		
		//get suspend_data of sources
		$a_s_lm=array();
		foreach ($a_ex as $key=>$value) {
			$tmp=explode('.',$value);
			if(!in_array($tmp[0],$a_s_lm)) $a_s_lm[]=$tmp[0];
		}
		$a_s_suspend = array();
		for ($i=0; $i<count($a_s_lm); $i++) {
			$tmp = self::getSuspend_data($a_s_lm[$i],$userId);
			if($tmp != "") $a_s_suspend[$a_s_lm[$i]] = $tmp;
		}
		if (count($a_s_suspend) == 0) return;
		
		//get essentials of target
		$a_t_suspend = self::getPatternEssentials($a_t_pattern["pattern"]);
		$t_suspend_new_ar = $a_t_suspend["p2_empty"];
		//get data of sources
		// $a_s_suspend_f=array();
		$a_s_suspend_p=array();
		foreach($a_s_suspend as $key => $value) { //key = slm_id; value=suspend_data
			$a_s_suspend_p[$key] = self::getPatternEssentials($value);
			$tmp=$a_s_suspend_p[$key]["p2_content_org"]; //content
			$c_ar = self::getContentAr($tmp); //content as array
			foreach($a_ex as $target=>$source){
				$tmp_s=explode('.',$source);
				if ($tmp_s[0]==$key) {
					$tmp_t=explode('.',$target);
					// $t_suspend_new_ar[$tmp_t[1]]="hi";
					$t_suspend_new_ar[$tmp_t[1]]=$c_ar[$tmp_s[1]];
				}
			}
		}
		// return;
		//save
		$suspend2insert=self::createNewSuspend(self::getPatternEssentials($a_t_pattern["pattern"]),$t_suspend_new_ar);
		self::insertTrackingData4Exchange($packageId, $userId, $suspend2insert);
	}

}