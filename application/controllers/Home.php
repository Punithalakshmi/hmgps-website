<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends CI_Controller {

	private $data = array('map_search_key'=>'','service_resp'=>array('status'=>''));

	protected $api_url = 'http://heresmygps.com/service/';
	protected $service_param = array('X-APP-KEY'=>'myGPs~@!');

	function __construct()
    {
       
        // Construct our parent class
        parent::__construct();
        $this->load->helper('cookie');
        $this->load->library('Rest');
     	
     	$this->rest->initialize(
			array('server' => $this->api_url,
		        'http_auth' => 'basic'
	    	)
		);
        
        $join_key = get_cookie('map_search');
        
		if($this->uri->segment(1)=='search' && $this->uri->segment(2)!=''){

			$cookie = array(
					'name' => 'map_search',
					'value' => $this->uri->segment(2),
					'expire' => time()+3600,
					'path'   => '/',
					);
				set_cookie($cookie);	
				$this->data['map_search_key'] = $this->uri->segment(2);
		}

		 $this->data['user_info'] = "";
	     $this->get_user_details();
       
     
    }

	public function index()
	{
		$this->layout->view('index',$this->data);
	}

	public function search($search='',$ajax=0)
	{
		try{

			$joinkey='';
			$user_id=0;
			
			$locations=array();

	   		$contents = array();

			$map_cookie = get_cookie('map_search');

			if(isset($_POST['search'])){
				$joinkey = $this->input->post('search');

				if(!$joinkey)
					throw new Exception("Please fill the required fields.");

				delete_cookie('map_search');
			}
			elseif($search!='')
			{
				$joinkey = $search;
			}	
			elseif($map_cookie!='')
			{
				$joinkey = $map_cookie;
			}	

			$user_id= get_cookie('map_user_id');

			if(!empty($user_id)){				
				$cookie = array(
						'name' => 'map_user_id',
						'value' => $user_id,
						'expire' => time()+86400,
						'path'   => '/',
						);
				set_cookie($cookie);

			}

			//set search value to cookie and expire time 1 hour
			if($joinkey!=''){	

				$cookie = array(
					'name' => 'map_search',
					'value' => $joinkey,
					'expire' => time()+3600,
					'path'   => '/',
					);
				set_cookie($cookie);	
				$this->data['map_search_key'] = $joinkey;	
			}
            $this->service_param['user_id'] = $user_id;
		    $this->service_param['join_key'] = $joinkey;
                
                
              //  print_r($this->service_param); exit;
	   		$map_det = $this->rest->get('search_map', $this->service_param, 'json');
            
	   		$map_det = (array)$map_det;
           
	   		if(empty($map_det))
	   			$map_det['status']='';

	   		$service_status = array('status'=>$map_det['status'],'message'=>'');

	   		if(!isset($_POST['search']) && $joinkey=='')
	   			$service_status['status']='';

	   		if($map_det['status']=='error')
	   			$service_status['message'] = $map_det['msg'];

	   		$this->data['service_resp'] = $service_status;


	   		if(isset($map_det['status']) && $map_det['status']=='success' && isset($map_det['members']) && !empty($map_det['members'])){

	   			foreach($map_det['members'] as $val){

	   				if($val->user->profile->flag==0)
	   					continue;

	   				$lastseen = date('d-m-Y H:i',$val->user->group->last_seen_time);

	   				$currtime = date('d-m-Y H:i',strtotime('-1 hour'));

	   				$lastup = 1;
	   				if(strtotime($currtime) >= strtotime($lastseen))
	   				{
	   					if($val->user->profile->user_type=='member')
	   						continue;

	   					$lastup = 0;
	   				}	

	   				$displayname = $val->user->profile->display_name;
	   				$channel_id  = $val->user->profile->default_id;
	   				$phone_num   = $val->user->profile->phonenumber;

	   				if($val->user->profile->user_type == 'admin'){
	   					$displayname = $val->user->group->description;
	   					$channel_id  = $val->user->group->join_key;
	   				}
	   			
                    $group_invisible = $val->user->group->invisible;	
	
	   				$locations[] = array($displayname,$val->user->position->lat,$val->user->position->lon,$lastup,$val->user->profile->user_type,$channel_id,$group_invisible);
	   			
	   				$img = base_url().'assets/image/default-user.png';

	   				if($val->user->profile->profile_image !='' && file_exists($val->user->profile->profile_image))
	   					$img = $val->user->profile->profile_image;

	   				$browseloc = 'http://maps.google.com/maps?z=12&t=m&q=loc:'.$val->user->position->lat.'+'.$val->user->position->lon;

                    $groups = $this->db->query("select * from groups where join_key='".$joinkey."'")->row_array();
                    
	   				$str  = '<div id="seach_content">
	   					       <span data-role="close" onclick="closeinfowindow()">X</span>
				             <div class="user_info">
							<div class="user">
								<img src="'.$img.'" alt="user-image" />
							</div>

							<div class="user_id">
								<h5>HMGPS User ID:<span><img src="'.base_url().'assets/image/user-id.jpg" />'.$channel_id.'</span></h5>
							    <h5>Display Name:<span><img src="'.base_url().'assets/image/display-name.jpg" />'.$displayname.'</span></h5>
							</div>
							</div>
							<hr />
							<div class="time_content">
								<h5>Position Time Updated<img src="'.base_url().'assets/image/clock.png" alt="clock" /></h5>
							    <p><<lastseen>></p>
							</div>
							<hr />
							<div class="speed">
								<div class="speed_value">
							    <h5>Speed</h5>
							    <p>'.$val->user->position->speed.'</p>
							    </div>
							    <div class="speed_value">
							    <h5>Accuracy</h5>
							    <p>'.$val->user->position->accuracy.'</p>
							    </div>
							</div>
							<hr />
							<div class="time_content">
								<h5>GPS Coordinate <img src="'.base_url().'assets/image/searchatlas-256.png" alt="seach-gps" /></h5>
							    <p>'.$val->user->position->lat.' , '.$val->user->position->lon.'</p>
							</div>
							<hr />

							<ul class="contact_card">
								<li><a href="'.$browseloc.'"><img src="'.base_url().'assets/image/04_maps.png" alt="map"/><span>Navigate</span></a></li>';
								
								if(is_numeric($phone_num)){
								$str .='<li><a href="tel:'.$phone_num.'"><img src="'.base_url().'assets/image/phone.png" alt="phone"/>'.$phone_num.'</a></li>
							          <li><a href="sms:'.$phone_num.'&body=Here\'s MyGPS" class="sms"><img src="'.base_url().'assets/image/sms.png" alt="sms" /></a></li>';
							    }

						 		$str .='<li><a href="mailto:?subject=Here\'s MyGPS&body=Hi, '.site_url('search/'.$channel_id).'"><img src="'.base_url().'assets/image/email_send.png" target="_blank" alt="email" /></a></li>';
                             if($this->data['user_info']['channel_id'] == $channel_id) {
                                    $str .='<li><a onclick="invisible_participant('.$val->user->profile->id.','.$groups['id'].')"><img src="'.base_url().'assets/image/invisible_participant_icon.png" target="_blank" alt="Invisible Participant" /></a></li>';
                                }   
							$str .= '</ul>

							</div>';
					
	   				$contents[] = array($str,$val->user->group->last_seen_time);		
	   			}

	   			//update current active group
	   			if((int)$user_id && $joinkey!=''){

	   				$this->service_param['user_id']  = $user_id;
	   				$this->service_param['group_id'] = $joinkey;
	   			    $res = $this->rest->get('user_current_group_active', $this->service_param, 'json');
	   			}	
	   		}	
	   	}
	   	catch (Exception $e)
        {
            $service_status = array('status'=>'error','message'=>$e->getMessage());
            $this->data['service_resp'] = $service_status;
        } 
        
       
	   	$this->data['user_id'] = $user_id;	
   		$this->data['locations'] =  json_encode($locations,JSON_PRETTY_PRINT);
   		$this->data['contents'] = json_encode($contents);

   		//ajax request rsponse
   		if($ajax==1){
   			echo json_encode(array('user_id'=>$user_id,'locations'=>$locations,'contents'=>$contents)); exit;
   		}
   		
        $userInfo = $this->data['user_info'];
        
        if(isset($_POST['search']) && ($_POST['search'] != $userInfo['joined_group'])){
     	  $this->get_user_details();
        }
        
        $this->user_joined_map($user_id,$joinkey);
        
		$this->layout->view('search',$this->data);
   		
	}


	public function aboutus()
	{
		$this->layout->view('aboutus',$this->data);
	}


	public function help()
	{
		$this->layout->view('help',$this->data);
	}


	public function tellus()
	{
		$this->layout->view('tellus',$this->data);
	}

     //invisible participant 
    function invisible_participant()
    {
        //user id
        $user_id = (isset($_POST['user_id']))?$_POST['user_id']:"";
        $grp_id  = (isset($_POST['group_id']))?$_POST['group_id']:"";
        
        $this->db->query("update user_groups set is_visible=1 where group_id='".$grp_id."' and user_id='".$user_id."'");
        
        echo "success";
        exit;
    } 
    
	function get_user_details(){

		 $this->service_param['user_id']  = get_cookie('map_user_id');

		if(!empty($this->service_param['user_id'])){

			$userchk = $this->rest->get('user_exist_check', $this->service_param, 'json');

			$userchk = (array)$userchk;

			if(!empty($userchk) && $userchk['status']=='error')
			{
				delete_cookie('map_user_id');

				$this->service_param['user_id'] = 0;
			}	
		}

	 $join_key = get_cookie('map_search');

		if(!empty($this->service_param['user_id']))
		{
			$this->service_param['join_key'] = $join_key;
			$userdet = $this->rest->get('get_channel_byuser', $this->service_param, 'json');

			$userdet = (array)$userdet;

			if(!empty($userdet) && $userdet['status']=='success')
			{	
				$this->data['user_info'] = array('channel_id' => $userdet['user']->channel_id, 'display_name'=>$userdet['user']->display_name, 'user_id'=>$userdet['user']->user_id,'group_id'=>$userdet['user']->group_id,'joined_group'=>$userdet['joined_group']);
			}
		}
		else
		{
			delete_cookie('map_search');
			$this->data['user_info'] = array('channel_id' => $this->random_channelid(), 'display_name'=>'', 'user_id'=>'','group_id'=>'','joined_group'=>'');
		}
	}
        
	function random_channelid(){
	   

		$random_id = get_cookie('rand_channelid');

		$rand = $this->rest->get('random_number_generation', $this->service_param, 'json');

		$rand = (array)$rand;
        
		if(is_array($rand) && !empty($rand))
		{
			$cookie = array(
					'name' => 'rand_channelid',
					'value' => $rand['random_id'],
					'expire' => time()+20000,
					'path'   => '/',
					);
			set_cookie($cookie);

			$random_id = $rand['random_id'];

		}				
		
		return $random_id;

	}


	public function guest_registration()
	{
		$latlong = $this->input->post('latlon');
		$phone   = $this->input->post('phone');
		$display = $this->input->post('display');
        $type    = $this->input->post('type');

		//$latlong = explode(":",$latlong);
        $latlong = (!empty($type) && ($type == 'current'))?explode(":",$latlong):convert_latlon($latlong);

		$outputs=array();

		if(count($latlong) <= 1){
			$latlong[0] = 'noloc';
			$latlong[1] = 'noloc';
		}
			

		$this->service_param['default'] 	= $phone;
		$this->service_param['email'] 		= '';
		$this->service_param['phonenumber'] = $phone;
		$this->service_param['password'] 	= '';
		$this->service_param['user_plan'] 	= 'guest';
		$this->service_param['profile_image']= '';
		$this->service_param['display_name']= ($display)?$display:$phone;
		$this->service_param['device_id']	= '';
		

   		$register = $this->rest->get('user_register', $this->service_param, 'json');
   		$register = (array)$register;

   		if(!empty($register) && isset($register['status'])){

   			if($register['status']=='success'){

	   			//user position save
	   			$this->user_position_save($register['user_id'],$latlong[0],$latlong[1],FALSE);
	   			

	   			$cookie = array(
					'name' => 'map_user_id',
					'value' => $register['user_id'],
					'expire' => time()+86400,
					'path'   => '/',
					);
				set_cookie($cookie);	

				$outputs['status']='success';
				$outputs['msg']   ='Successfully registerd as a guest or existing user!';
				$outputs['join_key']= (get_cookie('map_search')!='')?get_cookie('map_search'):'';

	   		}	
	   		else
	   		{
	   			$outputs['status']='error';
				$outputs['msg']   = $register['msg'];
	   		}

	   	}
	   	else
	   	{
	   		$outputs['status']='error';
			$outputs['msg']   = 'Bad request please try after some time! ';
	   	}	
		
		
		echo json_encode($outputs);
		exit;	
	}

	function user_update()
	{
	 
		$latlong     = $this->input->post('latlon');
		$phone       = $this->input->post('phone');
		$display     = $this->input->post('display');
		$group_id    = $this->input->post('group_id');
		$prev_channel= $this->input->post('prev_channel');

		$type        = $this->input->post('type');
        
        $latlong     = (!empty($type) && ($type == 'current'))?explode(":",$latlong):convert_latlon($latlong);

		$outputs=array();

		
		$this->service_param['channel_id'] 	 = $phone;
		$this->service_param['display_name'] = $display; 			
		$this->service_param['group_id']     = $group_id;
		$this->service_param['user_id']      = get_cookie('map_user_id');

		$updat = $this->rest->get('update_groupanduser', $this->service_param, 'json');	
		
   		$updat = (array)$updat;

   		if(!empty($updat) && isset($updat['status'])){

   			if($updat['status']=='success'){

				$outputs['status']='success';
				$outputs['msg']   ='Channel details updated successfully';
				$outputs['join_key'] = (get_cookie('map_search')==$prev_channel)?$phone:'';

				if($latlong[0]!==null && $latlong[1]!==null){
				
					$this->user_position_save(get_cookie('map_user_id'),$latlong[0],$latlong[1],FALSE);
				}
					
	   		}	
	   		else
	   		{
	   			$outputs['status']='error';
				$outputs['msg']   = $register['msg'];
	   		}

	   	}
	   	else
	   	{
	   		$outputs['status']='error';
			$outputs['msg']   = 'Bad request please try after some time! ';
	   	}	
		
		

		echo json_encode($outputs);
		exit;	
	}

	function user_position_save($user_id,$lat,$lon,$resp=TRUE)
	{
		if((int)$user_id && $lat!='' && $lon!='')
		{

			$this->service_param['user_id'] 	= $user_id;
			$this->service_param['lat'] 		= $lat;
			$this->service_param['lon'] 		= $lon;
			$this->service_param['accuracy'] 	= 0;
			$this->service_param['allow_empty'] = 1;

	   		$pos = $this->rest->get('user_position_save', $this->service_param, 'json');

	   		if($resp){
		   		echo json_encode($pos);
		   		exit;
	   		}
		}
	}

	public function create_group()
	{
		try{

			$latlong   = $this->input->post('latlon');
			$channel   = $this->input->post('channel');
			$display   = $this->input->post('display');
			$password  = $this->input->post('password');
			$type  	   = $this->input->post('type');
			$loc_type  = $this->input->post('loc_type');

			$outputs=array();


			$user_id=get_cookie('map_user_id');

			if(!(int)$user_id)
				throw new Exception("Please register and create map!");

			$latlong = explode(":",$latlong);

			$this->service_param['join_key'] 	= $channel;
			$this->service_param['name'] 		= $display;
			$this->service_param['description'] = $display;
			$this->service_param['password'] 	= $password;
			$this->service_param['type'] 	    = $type;
			$this->service_param['location_type']= $loc_type;
			$this->service_param['user_id']     = $user_id;
			$this->service_param['lat']			= $latlong[0];
			$this->service_param['lon']     	= $latlong[1];
			$this->service_param['map_avail']   = 'temporary';
			$this->service_param['is_available']= 1;
            $this->service_param['is_tracked']	= 1;

			
	   		$res = $this->rest->get('group_save', $this->service_param, 'json');
	   		$res = (array)$res;

	   		if(empty($res))
	   			throw new Exception('Service request failed!');

   			if($res['status']=='error')
   				throw new Exception($res['msg']);

   			$cookie = array(
				'name' => 'map_search',
				'value' => $res['join_key'],
				'expire' => time()+3600,
				'path'   => '/',
				);
			set_cookie($cookie);	  		

			$outputs['status']='success';
			$outputs['msg']   ='Group created successfully';
			$outputs['join_key'] =$res['join_key'];	

			
	   	}
	   	catch (Exception $e)
        {
        	$outputs['status']='error';
			$outputs['msg']   = $e->getMessage();
           
        } 	
		
		echo json_encode($outputs);
		exit;	
	}
    
    
    //check if user joined or not in searched map
    function  user_joined_map($user_id, $join_key)
    {
        
       	$this->service_param['join_key'] = $join_key;
        $this->service_param['user_id']  = $user_id;
        
        $map = $this->rest->get('check_user_joined_map', $this->service_param, 'json');
      
        $map = (array)$map;
        
		if(!empty($map) && $map['status']=='success'){	
			$this->data['maps'] = array('is_joined' => $map['is_joined'], 'group_id' => $map['group_id'], 'user_id' => $user_id);
		}
        
    }

   //delete group user 
   function delete_member($user_id,$group_id,$resp=TRUE) {
    
    if(!empty($user_id) && !empty($group_id)) {
        
        $this->service_param['group_id'] = $group_id;
        $this->service_param['user_id']  = $user_id;
        
        $map = $this->rest->get('delete_member', $this->service_param, 'json');
      
        $map = (array)$map;
        
        if($resp) {
    		if(!empty($map) && $map['status']=='success'){	
    		  $this->data['is_joined'] = 0;
    		   delete_cookie('map_search');
    		   echo 'success'; 
               exit;
    		}
       }
     } 
   }
   
   
	function test()
	{
		echo phpinfo();
		echo date('Y-m-d H:i:s')."=====".strtotime(date('Y-m-d H:i:s'))."<br/>";

	}
}
