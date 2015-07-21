<?php

function DeLog($text){
    file_put_contents("/tmp/ShipStationEndPoint.log",$text."\n",FILE_APPEND|LOCK_EX);
}

class ShipStation_Endpoint {
    
	protected $SS_Username = "";
	protected $SS_Password = "";
	
    // missing are null,F,W
	public $ShipStationStatusValues = array('E' => 'awaiting_payment', 
										  'P' => 'awaiting_shipment', 
										  'S' => 'shipped',
										  'C' => 'cancelled', 
										  'H' => 'on_hold');

	public $MemberStatusValues = array('E' => 'Entered',
										  'P' => 'Processed',
										  'S' => 'Shipped',
										  'C' => 'Cancelled', 
										  'H' => 'Holding');
	
	protected $SSDateFormat = "m/d/Y h:i:s A";
	
	// Add CData if it exists.
	public function CData($data){ return ($data == "") ? "":"<![CDATA[$data]]>"; }

	// check for GET data or return the default value.
	public function GetParam($Name,$Def){ return isset($_GET[$Name])? $_GET[$Name]:$Def; }
	
	// verify a path exists or create it.
	protected function CheckPath($Path){ if(!is_dir($Path)) mkdir($Path); }

	// Turn an array into a tag list.
	public function ArrayToXML($Ar){
		$Results = "";
		foreach($Ar as $Name => $Val){
			if(is_array($Val)){
				$Results .= "<$Name>".$this->ArrayToXML($Val)."</$Name>\n";
			} else {
				$Results .= "<$Name>".$Val."</$Name>\n";
			}
		}
		return $Results;
	}
	
	
	// Date Related Methods.
	protected function SQLDate($Date){ return date('Y-m-d H:i:s',strtotime($Date));	}
	
	public function DateMt2SS($Date){
		//if($Date == '0000-00-00') $Date = "Today"; // 0000-00-00 = jan 1 1970.
		return date($this->SSDateFormat,strtotime($Date));
	}

	// 
	//	Address Related methods.
	//

	// Member Address -> ShipStation Address.
	public function Address_MT2SS($Address){
		return array(
		"Name" => $this->CData($Address->Field["FirstName"]." ".$Address->Field["LastName"]) ,
		"Company" => $this->CData($Address->Field["Organization"]),
		"Address1" => $this->CData($Address->Field["Street"]),
		"Address2" => $this->CData($Address->Field["Street2"]),
		"City" => $this->CData($Address->Field["City"]),
		"State" => $this->CData($Address->Field["State"]),
		"PostalCode" => $this->CData($Address->Field["PostalCode"]),
		"Country" => $this->CData( ( !empty( $Address->Field["Country"] ) ? $Address->Field["Country"] : 'US' ) ),
		"Phone" => $this->CData($Address->Field["Telephone"]));
	}

	// ShipStation Address -> Member Address.
	protected function Address_SS2MT($Address){
		return array(
		//"Name" => $this->CData($Address->Field["FirstName"].", ".$Address->Field["LastName"]) ,
		"Organization" => $Address->Field["Company"],
		"Street" => $Address->Field["Address1"],
		"Street2" => $Address->Field["Address2"],
		"City" => $Address->Field["City"],
		"State" => $Address->Field["State"],
		"PostalCode" => $Address->Field["PostalCode"],
		"Country" => ( !empty( $Address->Field["Country"] ) ? $Address->Field["Country"] : 'US' ),
		"Telephone" => $Address->Field["Phone"]);
	}
	
	// 
	//	Item Related methods. 
	//

	// MemberItems -> ShipStation Items.
	public function OrderItems_MT2SS($Items){
		$ItemList = array();
		$Weight = 0;
		foreach($Items as $Item){
			$OW = $Item->Field["UnitWeight"] * 16;
			$Weight += ( $OW * $Item->Field["Quantity"] );
			$ItemList[] = array(
				"SKU"=> $this->CData($Item->Field["SKU"]),
				"Name"=> $this->CData($Item->Field["ProductName"]),
				//"ImageUrl"=> $this->CData(""),
				"Weight"=> $OW,
				"WeightUnits"=>"Ounces",
				"Quantity"=> $Item->Field["Quantity"],
				"UnitPrice"=> $Item->Field["UnitPrice"]
				//"Location"=> $this->CData(""),
				//"Options"=> ""
			);
		}
		return array("Items"=>$ItemList,"Weight"=>$Weight);
	}
	

	public function OrderMT2SS($Order){
	
		$Member = new Member($Order->Field["MemberId"]);
		$Status = "Holding"; // default is on hold..
		$MStat = @$this->MemberTekStatusValues[$Order->Field['Status']];
		if($MStat){
			$Status = $MStat;
		}
		$Contents = $this->OrderItems_MT2SS($Order->Items);
		
		// dates are tricky... 
		// mon/day/year hour:minutes:seconds AM/PM seems to be the accepted format.

	   $Data = array(
		'OrderID' => $this->CData($Order->Field['OrderId']) ,
		'OrderNumber' => $this->CData($Order->Field['Number']) ,
		'OrderDate' => $this->DateMt2SS($Order->Field['DateTime']),
		'OrderStatus' => $Status,
		'LastModified' => $this->DateMt2SS('Now'),
		'ShippingMethod'=> $Order->Field['ShippingMethod'],
		'OrderTotal' => $Order->Field['Total'],
		'PaymentMethod' => $this->CData('Credit Card'),
//		'AmountPaid' => $Order->Field['PaymentAmount'],
		'TaxAmount' => $Order->Field['SalesTax'],
		'ShippingAmount'=> $Order->Field['ShippingCharge'],
		'InternalNotes' => $this->CData('MemberId :'. $Order->Field['MemberId']),
		'Customer' => array(
				'CustomerCode' => $this->CData($Member->Field['Email']),
				'BillTo' => $this->Address_MT2SS($Order->BillingAddress),
				'ShipTo' => $this->Address_MT2SS($Order->ShippingAddress)),
		'Items' => ""// $Contents['Items']
		);
		foreach($Contents['Items'] as $Item){
			$Data['Items'] .= "<Item>".$this->ArrayToXML($Item)."</Item>\n";
		}
		
		if($Order->Field['CreditAmount'] > 0 ){
			$Data['CustomerNotes'] = $this->CData('Credits Used: '.$Order->Field['CreditAmount']);
		}
		
		// orders that are a gift are the only ones that need these set.
		if($Order->ShippingAddress->Field['Gift']){
		   $Data['Gift'] = $Order->ShippingAddress->Field['Gift'] == 1 ? 'true' : 'false';
		   $Data['GiftMessage'] = $this->CData($Order->ShippingAddress->Field['GiftMessage']);
		}
/*
		if($Order->Field['PaymentDate']){
		   $Data['PaymentDate'] = $this->DateMt2SS($Order->Field['PaymentDate']);
		}
*/		
		return $Data;
	}
	

// Actions.
	public function Action($action){
		DeLog("Action\n");
		switch($action){
			case 'export':
				$start = $this->GetParam("start_date",0);
				$end = $this->GetParam("end_date",0);
				$page = $this->GetParam("page",1);
				$this->Action_Export($start,$end,$page);
				break;
			case 'shipnotify':
				$order_number = $this->GetParam('order_number',0);
				$carrier = $this->GetParam('carrier',0);
				$service = $this->GetParam('service',0);
				$tracking_number = $this->GetParam('tracking_number',0);
				$this->Action_ShipNotify($order_number,$carrier,$service,$tracking_number);
				break;
			default:
				$this->Error($action);
				break;
		}
	}
	// Export action 
	// required StartDate, EndDate, Page, Page size is configurable
	public function Action_Export($Start,$End,$Page){
		DeLog("Action_Export( $Start,$End,$Page) ");	
		$PageSize = 900; // this is the amount of results per page.
		$PageStart = $PageSize * ( $Page - 1);
		$Qu = "SELECT OrderId FROM Orders WHERE DateTime BETWEEN '".$this->SQLDate($Start)."' AND '".
				$this->SQLDate($End)."' ORDER BY OrderId ASC LIMIT $PageStart,$PageSize";
		DeLog("Looking for Orders..$Qu");		
		$Res = DatabaseQuery($Qu);
		// first get the data from membertek.
		$Orders = array();
		while($Row = DatabaseFetchRow($Res)){
			DeLog("row :".print_r($Row,true));
			if($Row[0]){
				//echo "($Row[0])\n";
				$Order = new Order($Row[0]);
				$Orders[] = $this->ArrayToXML($this->OrderMT2SS($Order));
			}
		}
		
		// if there is any orders ie page isn't higher then there are pages.
		header('Content-Type: application/xml; charset=utf-8');
		echo "<?xml version=\"1.0\" encoding=\"utf-8\"?".">\n<Orders>\n";
	   	foreach($Orders as $Order){
			echo "<Order>\n".$Order."</Order>\n";
		}
		echo "</Orders>\n";
	}
	
	public function Action_ShipNotify($order_number,$carrier,$service,$tracking_number){

	   $Res = DatabaseQuery("UPDATE Orders SET Status = 'S' WHERE Number = '".$order_number."'");
	   $Get = DatabaseFetchRow( DatabaseQuery("SELECT `OrderId`, `MemberId` FROM `Orders` WHERE `Number` = '{$order_number}' LIMIT 1") );
	   $MemberId = $Get[ '1' ];
	   $orderId = $Get[ '0' ];
	   $Status = "Tracking Info $carrier, $service, $tracking_number";

	   History::Add('O', $orderId, $Status, $MemberId);

	   /*
	   $Res = DatabaseQuery("INSERT INTO `History` (`Type`, `Id`, `MemberId`, `Text`) VALUES ( 'O', '{$OrderId}', '{$MemberId}', '{$Text}') = '".$order_number."'");
	   
	   $ReplyPath = "/tmp/sslog";
	   $string = "Order :".$order_number."\n".
				"Carrier :".$carrier."\n".
				"Service :".$service."\n".
				"Tracking :".$tracking_number."\n".
				"Body\n----\n".$body."\n";
		*/	
		//echo "$string";
		//echo "$order_number -> Shipped\n";
		//$Year = date('Y'); $Mon = date('m'); $Day = date('d'); $Time = date('H_i_s');
		//$this->CheckPath("$ReplyPath/$Year"); 
		//$this->CheckPath("$ReplyPath/$Year/$Mon");
		//$this->CheckPath("$ReplyPath/$Year/$Mon/$Day");
		//file_put_contents("$ReplyPath/$Year_$Mon_$Day_$Time-".$order_number.".log",$string,LOCK_EX|FILE_APPEND);
		return;//no need to do anything more the 200 header is all ship station looks for anyways.
	}
	
	public function Error($action){
		$error = "Unknown action $action\n";
		print($error);

		$body = http_get_request_body();	
		$Year = date('Y'); $Mon = date('m'); $Day = date('d'); $Time = date('H_i_s');
		$this->CheckPath("$ReplyPath/$Year"); 
		$this->CheckPath("$ReplyPath/$Year/$Mon");
		$this->CheckPath("$ReplyPath/$Year/$Mon/$Day");
		file_put_contents("$ReplyPath/$Year/$Mon/$Day/error.log",$string.$body."\n----------------------------------\n" ,LOCK_EX|FILE_APPEND);
		
	}
};

	
