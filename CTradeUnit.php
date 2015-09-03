<?
	class CTradeUnit{
		const IB_TYPE = 'auction';
		const IB_CODE = 'units';
		const RATE_HISTORY = 409;
		var $id;//id лота
		var $name; //имя лота
		var $rate_history; //история ставок
		var $start_rate; //начальная ставка
		var $step; //шаг ставки
		var $description; //описание лота
		var $picture; //изображение лота
		var $start_time; //начало торгов
		var $finish_time; //окончание торгов
		var $current_rate; //текущая ставка
		var $message;
		var $status;
		var $winner; //победитель торгов
		var $detail_page; //url детальной страницы
		function __construct($id){
			$arSelect = Array("ID", "NAME", "DATE_ACTIVE_FROM","DATE_ACTIVE_TO","PREVIEW_TEXT","PREVIEW_PICTURE","PROPERTY_STEP","PROPERTY_START_RATE","PROPERTY_RATE_HISTORY","PROPERTY_WINNER","DETAIL_PAGE_URL");
			$arFilter = Array("IBLOCK_TYPE"=>self::getIBType(), "IBLOCK_CODE"=>self::getIBPointsTable(),"ACTIVE"=>"Y","ID"=>$id/*,"ACTIVE_DATE"=>"Y"*/);
			$res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
			if($arRes = $res->GetNext()){
				//print_r($arFilter);
				$img = CFile::GetFileArray($arRes["PREVIEW_PICTURE"]);
				$this->id = $arRes["ID"];
				$this->name = $arRes["NAME"];
				$this->rate_history = array_filter(explode(";",$arRes["PROPERTY_RATE_HISTORY_VALUE"]));
				$this->start_rate = $arRes["PROPERTY_START_RATE_VALUE"];
				$this->step = $arRes["PROPERTY_STEP_VALUE"];
				$this->description = $arRes["PREVIEW_TEXT"];
				$this->picture = $img["SRC"];
				$this->start_time = $arRes["DATE_ACTIVE_FROM"];
				$this->finish_time = $arRes["DATE_ACTIVE_TO"];
				$this->current_rate = $this->currentRate();
				$this->winner = intval($arRes["PROPERTY_WINNER_VALUE"]);
				$this->detail_page = $arRes["DETAIL_PAGE_URL"];
				if($this->winner <= 0){
					$finish = MakeTimeStamp($this->finish_time,"DD.MM.YYYY HH:MI:SS");
					$now = time();
					if($now>$finish)
					{
						$key = 778;
						$seg = sem_get($key);
						sem_acquire($seg);
						$log = new CLoger("construct");
						$ib_code = getIbIDByCode(self::getIBPointsTable());
						$win_prop = CIBlockElement::GetProperty($ib_code, $id, array("sort" => "asc"), Array("CODE"=>"WINNER"))->GetNext();
						$log->Add("win_prop",$win_prop);
						if($win_prop["VALUE"]<=0)
						{
							//sleep(10);
							$win_id = $this->winner();
							CIBlockElement::SetPropertyValues($id,$ib_code,$win_id,"WINNER");
						}
						sem_release($seg);
					}
				}

			}
		}
		//получение константы IB_TYPE
		public static function getIBType(){	return self::IB_TYPE;}
		//получение константы IB_CODE
		public static function getIBPointsTable(){	return self::IB_CODE;}
		//получение константы RATE_HISTORY
		public static function getRateHistory(){return self::RATE_HISTORY;}
		//добавление ставки к лоту
		function reRate($user_id){
			$arRates = $this -> rate_history;
			//смотрим на остаток времени
			$finish = MakeTimeStamp($this->finish_time,"DD.MM.YYYY HH:MI:SS");
			$now = time();
			$ban = CTrade::isBanned($user_id);
			if($ban["bool"] === true)
			{
				$this->status = "306";
				$this->message = $ban["fields"]["PREVIEW_TEXT"];
				return 0;
			}
			$days = 30;
			if(CTrade::joinLimit($days,$user_id) === true){
				$this->status = "305";
				$this->message = "Пользователь, выигравший приз на аукционе, не имеет право участвовать в розыгрыше другого лота в течении ".$days." календарных дней";
				return 0;
			}

			$key = 778;
			$seg = sem_get($key);
			if($now < $finish){
				//id предыдущего участника
				$id_last = $this->lastRateUser();
				if($id_last != $user_id){
					//получаем инфу текущего участника
					$filter = Array("ID"=>$user_id);
					$arUser = CUser::GetList(($by="personal_country"), ($order="desc"), $filter,array("SELECT"=>array("UF_POINTS")))->Fetch();
					//получаем инфу предыдущего участника
					$filter2 = Array("ID"=>$id_last);
					$arUser2 = CUser::GetList(($by="personal_country"), ($order="desc"), $filter2,array("SELECT"=>array("UF_POINTS")))->Fetch();

					if($this->current_rate==0){
						$this->current_rate = $this->start_rate-$this->step;
					}
					if($this->current_rate + $this->step <= $arUser["UF_POINTS"]){
						$arRates[] = $user_id;
						$new_val = implode(";",$arRates);
						//обновляем историю ставок
						CIBlockElement::SetPropertyValues($this->id,getIbIDByCode(self::getIBPointsTable()),$new_val,"RATE_HISTORY");
						//снимаем баллы с участника
						$user = new CUser;
						$new_val = $arUser["UF_POINTS"]-($this->step+$this->current_rate);
						$fields = Array(
								"UF_POINTS"=>$new_val,
						);
						$user->Update($arUser["ID"], $fields);
						//возвращаем баллы предыдущему участнику
						$user2 = new CUser;
						$new_val2 = $arUser = $arUser2["UF_POINTS"] + $this->current_rate;
						$fields2 = Array(
								"UF_POINTS"=>$new_val2,
						);
						$user2->Update($arUser2["ID"], $fields2);
						//
						$this->status = "200";
						$this->message = "Ваша ставка принята";
						$this->current_rate += $this->step;
						if($finish-$now<=300){
							$el = new CIBlockElement;
							$update_time = Array("DATE_ACTIVE_TO"=>ConvertTimeStamp($finish+300, "FULL"));
							$res = $el->Update($this->id, $update_time);
							if($res){
								return Array($id_last,$this->current_rate-$this->step,"uptime");
							}
						}else{
							return Array($id_last,$this->current_rate-$this->step);
						}
					}else{
						$this->status = "301";
						$this->message = "На Вашем счету недостаточно баллов";
						return 0;
					}
				}else{
					$this->status = "302";
					$this->message = "Ваша ставка - последняя";
					return 0;
				}
			}else{
				$this->status = "303";
				$this->message = "Время вышло. Ставки больше не принимаются";
			}
			sem_release($seg);
		}
		function currentRate(){
			$arRate = $this -> rate_history;
			$count=count($arRate);
			if($count>0){
				$currentRate = ($count-1)*($this->step) + $this->start_rate; //
			}else{
				$currentRate = 0;
			}
			return intval($currentRate);
		}
		function lastRateUser(){
			$ar = $this -> rate_history;
			return end($ar);
		}
		function winner(){
			$log = new CLoger("winner");
			$log -> Add("вызвали функцию: ".$_SERVER["SCRIPT_NAME"]);
			$finish = MakeTimeStamp($this->finish_time,"DD.MM.YYYY HH:MI:SS");
			$now = time();
			if($now>$finish){
				$ar = $this -> rate_history;
				$win_idx = count($ar)-1;
				$win_id = $ar[$win_idx];
					$filter = Array("ID"=>$win_id);//$filter = Array("ID"=>$user_id);
					$arUser = CUser::GetList(($by="personal_country"), ($order="desc"), $filter,array("SELECT"=>array("UF_POINTS")))->Fetch();
					$user = new CUser;

					if($arUser["ID"]==$win_id /* and $key == $win_idx*/){//отправляем письмо победителю
						$log->Add("winner:".$arUser["EMAIL"]." ".$arUser["NAME"]." ".$this->name." ".$arUser["UF_POINTS"]." ".$this->id.";");
						if(strlen($arUser["NAME"])>0){
							$name = "Уважаемый ".$arUser["NAME"]."!";
						}else{
							$name = "";
						}
						CEvent::SendImmediate
						(
							"AUCTION_WINNER",
							"s1",
							Array(
								"EMAIL_TO"    => $arUser["EMAIL"],
								"USER_NAME"   => $arUser["NAME"],
								"UNIT_NAME"   => $this->name,
								"USER_POINTS" => $arUser["UF_POINTS"],
								"UNIT_ID"     => $this->id
							),
							"N"
						);
					}
				$this->winner = $win_id;
				return $win_id;
			}else{
				return 0;
			}
		}
		public static function getTime($idArray){
			$arSelect = Array("ID", "DATE_ACTIVE_TO");
			$arFilter = Array("IBLOCK_TYPE"=>self::getIBType(), "IBLOCK_CODE"=>self::getIBPointsTable(),"ACTIVE"=>"Y","ID"=>$idArray/*,"ACTIVE_DATE"=>"Y"*/);
			$res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
			$i=0;
			while($arRes = $res->GetNext()){
				$timeAr[$arRes["ID"]] = MakeTimeStamp($arRes["DATE_ACTIVE_TO"],"DD.MM.YYYY HH:MI:SS")*1000;
			}
			return $timeAr;
		}
	}
