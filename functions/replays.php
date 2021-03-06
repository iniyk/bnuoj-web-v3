<?php
include_once(dirname(__FILE__)."/problems.php");
include_once(dirname(__FILE__)."/users.php");

function replay_to_second($str) {
    $h=intval(strstr($str,':',true));
    $str=substr(strstr($str,':'),1);
    $m=intval(strstr($str,':',true));
    $str=substr(strstr($str,':'),1);
    $s=intval($str);
    return $h*3600+$m*60+$s;
}

function replay_add_contest() {
    global $pnum,$_POST,$mcid,$db,$config;
    $sql="insert into contest (title,description,start_time,end_time,type,isvirtual) values ('".$_POST['name']."','".$_POST['description']."','".$_POST['start_time']."','".$_POST['end_time']."','99','".$_POST['isvirtual']."')";
    $db->query($sql);
    for ($i=0;$i<$pnum;$i++) {
        $sql="insert into contest_problem (lable,pid,cid) values ('".$_POST['lable'.$i]."','".$_POST['pid'.$i]."','$mcid')";
        $db->query($sql);
    }
    $cres=$db->query("select problem.title from contest_problem,problem where cid=".$mcid." and contest_problem.pid=problem.pid");
    $str=array();
    foreach ( (array) $db->get_results(null,ARRAY_N) as $crow) {
        $str[]=trim(strtolower($crow[0]));
    }
    sort($str);
    $db->query("update contest set allp='".md5(implode($str,$config["salt_problem_in_contest"]))."' where cid=".$mcid);
}

function insone($pid,$res,$dtime,$cid,$name) {
    global $db;
    $sql="insert into replay_status (pid,result,time_submit,contest_belong,username) values 
        ('".$pid."','".$res."','".$dtime."','".$cid."','".$name."')";
    $db->query($sql);
}

function inswa($tnum,$sttime,$edtime,$pid,$name,$mcid,$pert=10) {
    for ($q=$tnum;$q>=1;$q--) {
        $inst=$edtime-$pert*$q;
        if ($inst<=$sttime+5) $inst=$sttime+5;
        insone($pid,'No',date("Y-m-d H:i:s",$inst),$mcid,$name);
    }
}

function insac($tnum,$sttime,$act,$pid,$name,$mcid,$pert=10) {
    for ($q=$tnum;$q>=1;$q--) {
        $inst=$sttime+$act-$pert*$q;
        if ($inst<=$sttime+5) $inst=$sttime+5;
        insone($pid,'No',date("Y-m-d H:i:s",$inst),$mcid,$name);
    }
    insone($pid,'Accepted',date("Y-m-d H:i:s",$sttime+$act),$mcid,$name);
}


function replay_move_uploaded_file($filename) {
    global $_FILES,$_POST,$ret;
    if (sizeof($_FILES)!=0) {
        move_uploaded_file($_FILES["file"]["tmp_name"], "../uploadstand/" . $filename);
    }
    else {
        $tuCurl=curl_init();
        curl_setopt($tuCurl,CURLOPT_URL,$_POST["repurl"]);
        curl_setopt($tuCurl,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($tuCurl,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($tuCurl,CURLOPT_USERAGENT,"BNUOJ");
        $html=curl_exec($tuCurl);
        curl_close($tuCurl);
        file_put_contents("../uploadstand/" . $filename,$html);
    }
    $ret["msg"].="Stored in: " . "uploadstand/" . $filename."<br />";
}

function replay_deal_hdu($data) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $unum=$data->rowcount()-2;
    for ($i=3;$i<=$unum;$i++) {
        $uname=$data->val($i,2);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($data->val($i,$j+5));
            if ($value=="") continue;
            if ($value[0]=='(') {
                $tnum=intval(substr($value,2,-1));
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=0;
//                echo $uname." ".$_POST['pid'.$j]." ".$value."<br />\n";
                if (strstr($value,'(')) {
                    $act=strstr($value,'(',true);
                    $tnum=substr(strstr($value,'('),2,-1);
                }
                else $act=$value;
//                echo $uname." ".$_POST['pid'.$j]." ".date("Y-m-d H:i:s",$sttime+replay_to_second($act)-10)." * $tnum + ".date("Y-m-d H:i:s",$sttime+replay_to_second($act))."<br />\n";
                insac($tnum,$sttime,replay_to_second($act),$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_myexcel($data) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $unum=$data->rowcount();
    for ($i=2;$i<$unum;$i++) {
        $uname=$data->val($i,1);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($data->val($i,$j+2));
            if ($value=="0/--"||$value=="") continue;
            if (stripos($value,":")===false&&stripos($value,"(")===false) {
                if (strstr($value,'--')===false) {
                    $tnum=strstr($value,'/',true);
                    $act=intval(substr(strstr($value,'/'),1));
    //                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$sttime+$act*60)."<br />\n";
                    insac($tnum-1,$sttime,intval($act)*60,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
                }
                else {
                    $tnum=strstr($value,'/',true);
    //                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                    inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
                }
            }
            else {
                if ($value[0]=='(') {
                    $tnum=intval(substr($value,2,-1));
    //                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                    inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
                }
                else {
                    $tnum=0;
    //                echo $uname." ".$_POST['pid'.$j]." ".$value."<br />\n";
                    if (strstr($value,'(')) {
                        $act=strstr($value,'(',true);
                        $tnum=substr(strstr($value,'('),2,-1);
                    }
                    else $act=$value;
    //                echo $uname." ".$_POST['pid'.$j]." ".date("Y-m-d H:i:s",$sttime+replay_to_second($act)-10)." * $tnum + ".date("Y-m-d H:i:s",$sttime+replay_to_second($act))."<br />\n";
                    insac($tnum,$sttime,replay_to_second($act),$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
                }
            }
        }
    }
}

function replay_deal_zju($data) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $unum=$data->rowcount();
    for ($i=7;$i<=$unum;$i++) {
        $uname=$data->val($i,2);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($data->val($i,$j+5));
            if ($value=="") continue;
            if (strstr($value,'(')==null) {
                $tnum=intval($value);
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=0;
                $act=intval(strstr($value,'(',true))*60;
                $tnum=intval(substr(strstr($value,'('),1,-1))-1;
//                echo $uname." ".$_POST['pid'.$j]." ".date("Y-m-d H:i:s",$sttime+$act-10)." * $tnum + ".date("Y-m-d H:i:s",$sttime+$act)."<br />\n";
                insac($tnum,$sttime,$act,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_zjuhtml($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum-1;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[1]->innertext);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+3]->innertext);
            if ($value=="0") continue;
            if (strstr($value,'(')==null) {
                $tnum=intval($value);
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=0;
                $act=intval(strstr($value,'(',true))*60;
                $tnum=intval(substr(strstr($value,'('),1,-1))-1;
//                echo $uname." ".$_POST['pid'.$j]." ".date("Y-m-d H:i:s",$sttime+$act-10)." * $tnum + ".date("Y-m-d H:i:s",$sttime+$act)."<br />\n";
                insac($tnum,$sttime,$act,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_licstar($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum-1;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[3]->innertext." ".$crow[1]->innertext);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+6]->innertext);
            if ($value=="") continue;
            if (strstr($value,'/')) {
                $tnum=strstr($value,'/',true);
                $act=intval(substr(strstr($value,'/'),1));
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$sttime+$act*60)."<br />\n";
                insac($tnum-1,$sttime,intval($act)*60,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=intval($value);
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_jhinv($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum-2;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[2]->innertext." ".$crow[1]->innertext);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+6]->innertext);
            if ($value=="0/--") continue;
            if (strstr($value,'--')===false) {
                $tnum=strstr($value,'/',true);
                $act=intval(substr(strstr($value,'/'),1));
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$sttime+$act*60)."<br />\n";
                insac($tnum-1,$sttime,intval($act)*60,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=strstr($value,'/',true);
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_ctu($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum;
    $extinfo=array();
    for ($i=0;$i<$pnum;$i++) $extinfo[strtolower($_POST['extrainfo'][$i])]=$i;
//    var_dump($extinfo);die();
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=0;$i<$unum;$i++) {
        if ($rows[$i]->find("th",0)!=null) continue;
        $crow=$rows[$i]->children();
        $uname=$crow[3]->innertext;
        $pid=$_POST['pid'.$extinfo[strtolower(substr(strstr($crow[0]->find("a",0)->href,"/"),1,1))]];
        $act=date("Y-m-d H:i:s",$sttime+replay_to_second($crow[2]->innertext));
        $res=$crow[5]->innertext;
        if (stristr($res,"accepted")) $res="Accepted";
        else if (stristr($res,"ignored")) continue;
        else $res="No";
//        echo "$uname $pid $res $act <br />";
        insone($pid,$res,$act,$mcid,$uname);
    }
}

function replay_deal_ural($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum-2;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[2]->innertext);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+3]->innertext);
            if ($value=="") continue;
            if (strstr($value,'+')) {
                $tnum=substr(strstr($value,'<',true),1);
                $act=$crow[$j+3]->find("i",0)->innertext.":0";
                //echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$sttime+replay_to_second($act))."<br />\n";
                insac($tnum,$sttime,replay_to_second($act),$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                //echo $value;
                $tnum=substr(strstr($value,'<',true),3);
                $wat=$crow[$j+3]->find("i",0)->innertext.":0";
                //echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$sttime+replay_to_second($wat))."<br />\n";
                inswa($tnum,$sttime,$sttime+replay_to_second($wat),$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_neerc($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=2;$i<$unum-4;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[0]->innertext);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+1]->innertext);
            if ($value==".") continue;
            if (strstr($value,"-")!=null) {
                $value=strip_tags($value);
                $tnum=-intval($value);
                //echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=0;
                $act="0:".trim(strip_tags(strstr($value,'<br>')));
                //echo $act;
                $tnum=strip_tags(strstr(strstr($value,'+'),"<br>",true));
                if ($tnum=="") $tnum=0;
                else $tnum=intval($tnum);
                //echo $uname." ".$_POST['pid'.$j]." ".date("Y-m-d H:i:s",$sttime+replay_to_second($act)-10)." * $tnum + ".date("Y-m-d H:i:s",$sttime+replay_to_second($act))."<br />\n";
                insac($tnum,$sttime,replay_to_second($act),$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_2011shstatus($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum;
    $extinfo=array();
    for ($i=0;$i<$pnum;$i++) $extinfo[strtolower($_POST['extrainfo'][$i])]=$i;
//    var_dump($extinfo);die();
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum;$i++) {
        $crow=$rows[$i]->children();
        $uname=$crow[1]->innertext." ".$crow[2]->innertext;
        $pid=$_POST['pid'.$extinfo[strtolower($crow[3]->innertext)]];
        $act=date("Y-m-d H:i:s",$sttime+60*intval($crow[4]->innertext));
        $res=$crow[5]->innertext;
        if (stristr($res,"Yes")) $res="Accepted";
        else $res="No";
//        echo "$uname $pid $res $act <br />";
        insone($pid,$res,$act,$mcid,$uname);
    }
}

function replay_deal_icpcinfostatus($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum;
    $extinfo=array();
    for ($i=0;$i<$pnum;$i++) $extinfo[strtolower($_POST['extrainfo'][$i])]=$i;
//    var_dump($extinfo);die();
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum;$i++) {
        $crow=$rows[$i]->children();
        $uname=$crow[1]->innertext;
        $pid=$_POST['pid'.$extinfo[strtolower($crow[0]->innertext)]];
        $act=date("Y-m-d H:i:s",$sttime+60*intval($crow[3]->innertext));
        $res=$crow[2]->innertext;
        if (stristr($res,"Yes")) $res="Accepted";
        else $res="No";
//        echo "$uname $pid $res $act <br />";
        insone($pid,$res,$act,$mcid,$uname);
    }
}

function replay_deal_pc2sum($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum-1;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[1]->innertext);
        if ($uname=="") continue;
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+4]->innertext);
            if ($value=="0/--") continue;
            if (strstr($value,'--')===false) {
                $tnum=strstr($value,'/',true);
                $act=intval(substr(strstr($value,'/'),1));
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$sttime+$act*60)."<br />\n";
                insac($tnum-1,$sttime,intval($act)*60,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=strstr($value,'/',true);
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_fdulocal2012($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum-1;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[2]->innertext);
        if ($uname=="") continue;
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+9]->innertext);
            if ($value=="0/--") continue;
            if (strstr($value,'--')===false) {
                $tnum=strstr($value,'/',true);
                $act=intval(substr(strstr($value,'/'),1));
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$sttime+$act*60)."<br />\n";
                insac($tnum-1,$sttime,intval($act)*60,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=strstr($value,'/',true);
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_uestc($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[1]->innertext);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+4]->innertext);
            if ($value=="") continue;
            if (strstr($value,'(')!==false) {
                if (strstr($value,'-')===false) {
                    $act=intval(strstr($value,'<',true))*60;
                    $tnum=intval(substr(strstr($value,'('),1,-1));
//                    echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$sttime+$act)."<br />\n";
                    insac($tnum-1,$sttime,intval($act),$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
                }
                else {
                    $tnum=intval(substr(strstr($value,'-'),1,-1));
//                    echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                    inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
                }
            }
            else {
                if (strstr($value,'--')===false) {
                    $tnum=strstr($value,'/',true);
                    $act=intval(substr(strstr($value,'/'),1));
//                    echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$sttime+$act*60)."<br />\n";
                    insac($tnum-1,$sttime,intval($act)*60,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
                }
                else {
                    $tnum=strstr($value,'/',true);
//                    echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                    inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
                }
            }
        }
    }
}



function replay_deal_hustvjson($html) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $statarr=json_decode($html);
/*    echo "<pre>";
    var_dump($statarr);
    echo "</pre>";*/
    $username=array();
    foreach ($statarr[1] as $id => $info) $username[$id]=strip_tags($info[0]);
//    die();
    for ($i=2;$i<sizeof($statarr);$i++) {
//        var_dump($statarr[$i]);
        $uname=$username[$statarr[$i][0]];
        if ($uname=="") $uname=$statarr[$i][0];
        $value=$statarr[$i][0];
        $pid=$_POST['pid'.$statarr[$i][1]];
        $act=date("Y-m-d H:i:s",$sttime+intval($statarr[$i][3]));
        $res=$crow[5]->innertext;
        if ($statarr[$i][2]==1) $res="Accepted";
        else $res="No";
//        echo "$uname $pid $res $act <br />";
        insone($pid,$res,$act,$mcid,$uname);

    }
}


function replay_deal_fzuhtml($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[1]->innertext);
        if ($uname=="") continue;
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+4]->plaintext);
            if ($value=="-/--") continue;
            if (strstr($value,'--')===false) {
                $tnum=strstr($value,'/',true);
                $act=intval(substr(strstr($value,'/'),1));
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$sttime+$act*60)."<br />\n";
                insac($tnum-1,$sttime,intval($act)*60,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=strstr($value,'/',true);
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_usuhtml($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr.row0, tr.row1");
    $unum=sizeof($rows);
    for ($i=0;$i<$unum;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[1]->innertext);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+2]->innertext);
            if ($value=="") continue;
            //echo htmlspecialchars($crow[$j+2]->find("span",1));
            if (strstr($value,"-")!=null) {
                $value=trim(strstr($value,"<",true));
                $tnum=-intval($value);
                $wat=$crow[$j+2]->find("span",0)->plaintext.":0";
                //echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".$wat."<br />\n";
                inswa($tnum,$sttime,$sttime+replay_to_second($wat),$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=0;
                $act=$crow[$j+2]->find("span",1)->plaintext.":0";
                //echo $act;
                $tnum=trim(strip_tags(strstr(strstr($value,'+'),"<",true)));
                if ($tnum=="") $tnum=0;
                else $tnum=intval($tnum);
                //echo $uname." ".$_POST['pid'.$j]." ".date("Y-m-d H:i:s",$sttime+replay_to_second($act)-10)." * $tnum + ".$act."<br />\n";
                insac($tnum,$sttime,replay_to_second($act),$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_sguhtml($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[2]->innertext);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+5]->innertext);
            if (trim(strip_tags($value))=="-") continue;
            if (strstr($value,"-")!=null) {
                $value=strip_tags($value);
                $tnum=-intval($value);
                //echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=0;
                $act=trim(strip_tags(strstr($value,'<br>'))).":0";
                //echo $act;
                $tnum=strip_tags(strstr(strstr($value,'+'),"<br>",true));
                if ($tnum=="") $tnum=0;
                else $tnum=intval($tnum);
                //echo $uname." ".$_POST['pid'.$j]." ".date("Y-m-d H:i:s",$sttime+replay_to_second($act)-10)." * $tnum + ".date("Y-m-d H:i:s",$sttime+replay_to_second($act))."<br />\n";
                insac($tnum,$sttime,replay_to_second($act),$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_amt2011($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=2;$i<$unum;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[1]->innertext);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+3]->innertext);
            if (trim(strip_tags($value))=="&nbsp;") continue;
            if (strstr($value,'-')!=null) {
                $tnum=intval(strstr(substr(strstr($value,"("),1),")",true));
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=0;
                $act=replay_to_second(strstr($value,'(',true));
                $tnum=intval(substr(strstr($value,'('),1,-1));
//                echo $uname." ".$_POST['pid'.$j]." ".date("Y-m-d H:i:s",$sttime+$act-10)." * $tnum + ".date("Y-m-d H:i:s",$sttime+$act)."<br />\n";
                insac($tnum,$sttime,$act,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_nwerc($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum-1;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[2]->innertext);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+5]->innertext);
            if (trim(strip_tags($value))=="0") continue;
            if (strstr($value,'(')==null) {
                $tnum=intval($value);
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=0;
                $act=intval(trim(strstr(substr(strstr($value,'('),1),'+',true)))*60;
                $tnum=intval(trim(strstr($value,'(',true)))-1;
//                echo $uname." ".$_POST['pid'.$j]." ".date("Y-m-d H:i:s",$sttime+$act-10)." * $tnum + ".date("Y-m-d H:i:s",$sttime+$act)."<br />\n";
                insac($tnum,$sttime,$act,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_ncpc($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum-4;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[1]->innertext);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+6]->innertext);
            if ($value=="") continue;
            if (strstr($value,"-")!=null) {
                $value=trim(strstr($value,'<',true));
                $tnum=intval($value)-1;
                //echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=0;
                $act=intval(trim(strip_tags(strstr($value,"<small>"))))*60;
                //echo $act;
                $tnum=trim(strstr($value,'<',true))-1;
                //echo $uname." ".$_POST['pid'.$j]." ".date("Y-m-d H:i:s",$sttime+replay_to_second($act)-10)." * $tnum + ".date("Y-m-d H:i:s",$sttime+replay_to_second($act))."<br />\n";
                insac($tnum,$sttime,$act,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_uva($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum-1;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[1]->innertext);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+2]->innertext);
            if (trim(strip_tags($value))=="00:00:00\t\t\t\t(0)") continue;
            if (strstr($value,"00:00:00")) {
                $tnum=intval(strstr(substr(strstr($value,"("),1),")",true));
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=0;
                $act=replay_to_second(trim(strstr($value,'(',true)));
                $tnum=intval(strstr(substr(strstr($value,"("),1),")",true));
//                echo $uname." ".$_POST['pid'.$j]." ".date("Y-m-d H:i:s",$sttime+$act-10)." * $tnum + ".date("Y-m-d H:i:s",$sttime+$act)."<br />\n";
                insac($tnum,$sttime,$act,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_gcpc($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum-2;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[2]->innertext);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+5]->innertext);
            if ($value=="0") continue;
            if (strstr($value,"+")==null) {
                $tnum=intval($value);
                //echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=0;
                $act=intval(trim(strstr(substr(strstr($value,"("),1),'+',true)))*60;
                //echo $act;
                $tnum=trim(strstr($value,'(',true));
                //echo $uname." ".$_POST['pid'.$j]." ".date("Y-m-d H:i:s",$sttime+replay_to_second($act)-10)." * $tnum + ".date("Y-m-d H:i:s",$sttime+replay_to_second($act))."<br />\n";
                insac($tnum,$sttime,$act,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_phuket($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("li");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum-1;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[2]->innertext." ".$crow[1]->innertext);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[3]->find("div",$j+2)->innertext);
            if ($value=="0/-") continue;
            if (strstr($value,'-')===false) {
                $tnum=strstr($value,'/',true);
                $act=intval(substr(strstr($value,'/'),1));
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$sttime+$act*60)."<br />\n";
                insac($tnum-1,$sttime,intval($act)*60,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=strstr($value,'/',true);
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_spacific($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=0;$i<$unum-1;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[1]->innertext);
        //echo $uname." ---<br>";
        if ($uname=="") continue;
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+5]->innertext);
            if ($value=="0/--") continue;
            if (strstr($value,'--')===false) {
                $tnum=strstr($value,'/',true);
                $act=intval(substr(strstr($value,'/'),1));
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$sttime+$act*60)."<br />\n";
                insac($tnum-1,$sttime,intval($act)*60,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=strstr($value,'/',true);
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_spoj($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[1]->innertext);
        //echo $uname." ---<br>";
        if ($uname=="") continue;
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+2]->innertext);
            if ($value=="-") continue;
            if (strstr($value,'-')!==false) {
                $tnum=strstr(substr(strstr($value,'('),1),')',true);
                $act=strtotime(strstr($value,'(',true));
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$act)."<br />\n";
                insac($tnum,$sttime,$act-$sttime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=strstr(substr(strstr($value,'('),1),')',true);
//                echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_openjudge($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[1]->innertext);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim(strip_tags($crow[$j+4]->innertext));
            if ($value=="") continue;
            if (strstr($value,":")==null) {
                $tnum=strstr(substr(strstr($value,'('),1),')',true);
                $tnum=-intval($tnum);
                //echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=0;
                if (strstr($value,"(")==null) $act=$value;
                else {
                    $act=strstr($value,'(',true);
                    $tnum=strstr(substr(strstr($value,'('),1),')',true);
                }
                if ($tnum=="") $tnum=0;
                else $tnum=-intval($tnum);
                //echo $uname." ".$_POST['pid'.$j]." ".date("Y-m-d H:i:s",$sttime+replay_to_second($act)-10)." * $tnum + ".date("Y-m-d H:i:s",$sttime+replay_to_second($act))."<br />\n";
                insac($tnum,$sttime,replay_to_second($act),$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_scu($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum-1;$i++) {
        $crow=$rows[$i]->children();
        $uname=iconv("GBK","UTF-8//IGNORE",strip_tags($crow[2]->innertext));
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+5]->innertext);
            if ($value=="") continue;
            if (strstr($value,":")==null) {
                $tnum=$value;
                $tnum=-intval($tnum);
                //echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=0;
                $act=strstr($value,'<',true).":00";
                $tnum=substr(strstr($value,'>'),1);
                $tnum=intval($tnum);
                //echo $uname." ".$_POST['pid'.$j]." ".date("Y-m-d H:i:s",$sttime+replay_to_second($act)-10)." * $tnum + ".date("Y-m-d H:i:s",$sttime+replay_to_second($act))."<br />\n";
                insac($tnum,$sttime,replay_to_second($act),$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_deal_hust($standtable) {
    global $_POST,$sttime,$edtime,$mcid,$pnum,$sfreq;
    $rows=$standtable->find("tr");
    $unum=sizeof($rows);
    for ($i=1;$i<$unum;$i++) {
        $crow=$rows[$i]->children();
        $uname=strip_tags($crow[1]->innertext);
        for ($j=0;$j<$pnum;$j++) {
            $value=trim($crow[$j+4]->innertext);
            if ($value=="") continue;
            if (strstr($value,":")==null) {
                $tnum=strstr(substr(strstr($value,'('),1),')',true);
                $tnum=-intval($tnum);
                //echo $uname." ".$_POST['pid'.$j]." ".$tnum." * ".date("Y-m-d H:i:s",$edtime-10)."<br />\n";
                inswa($tnum,$sttime,$edtime,$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
            else {
                $tnum=0;
                if (strstr($value,"(")==null) $act=$value;
                else {
                    $act=strstr($value,'(',true);
                    $tnum=strstr(substr(strstr($value,'('),1),')',true);
                }
                if ($tnum=="") $tnum=0;
                else $tnum=-intval($tnum);
                //echo $uname." ".$_POST['pid'.$j]." ".date("Y-m-d H:i:s",$sttime+replay_to_second($act)-10)." * $tnum + ".date("Y-m-d H:i:s",$sttime+replay_to_second($act))."<br />\n";
                insac($tnum,$sttime,replay_to_second($act),$_POST['pid'.$j],convert_str($uname),$mcid,$sfreq);
            }
        }
    }
}

function replay_crawl_zju($cid) {
    $res=array();
    $html=file_get_html("http://acm.zju.edu.cn/onlinejudge/showContestProblems.do?contestId=$cid");
    if ($html->find("div.message")!=null) {
        $res["code"]=1;
        return $res;
    }
    $titles=$html->find("table.list td.problemTitle font");
    for ($i=0;$i<sizeof($titles);$i++) {
        $tname=problem_get_id_from_oj_and_title("ZJU",trim($titles[$i]->innertext));
        if ($tname==null) {
            $res["code"]=1;
            return $res;
        }
        $res["vpid$i"]=$tname;
    }
    $html=file_get_html("http://acm.zju.edu.cn/onlinejudge/contestInfo.do?contestId=$cid");
    $sttime=trim(strstr($html->find(".dateLink",0)->plaintext,"(",true));
    $length=$html->find(".contestInfoTable tr", 3)->find("td",1)->plaintext;
    $edtime=date("Y-m-d H:i:s",strtotime($sttime)+strtotime($length)-time());
    $title=$html->find(".contestInfoTable tr", 1)->find("td",1)->plaintext;
    $res["start_time"]=$sttime;
    $res["end_time"]=$edtime;
    $res["name"]=$title;
    $res["description"]=$res["repurl"]="http://acm.zju.edu.cn/onlinejudge/showContestRankList.do?contestId=$cid";
    $res["ctype"]="zjuhtml";
    $res["code"]=0;
    $res["isvirtual"]=0;
    return $res;
}

function replay_hustv_convert($oj) {
    if ($oj=="POJ") return "PKU";
    if ($oj=="ZOJ") return "ZJU";
    return $oj;
}

function replay_crawl_hustv($cid) {
    $res=array();
    $tuCurl=curl_init();
    curl_setopt($tuCurl,CURLOPT_URL,"http://acm.hust.edu.cn/vjudge/contest/view.action?cid=$cid");
    curl_setopt($tuCurl,CURLOPT_RETURNTRANSFER,1);
    curl_setopt($tuCurl,CURLOPT_FOLLOWLOCATION,1);
    curl_setopt($tuCurl,CURLOPT_USERAGENT,"BNUOJ");
    $html=curl_exec($tuCurl);
    curl_close($tuCurl);
    $html=str_get_html($html);
    if ($html->find("#viewContest")==null) {
        $res["code"]=1;
        return $res;
    }
    $titles=$html->find("#viewContest td.center a");
    $res["pnum"]=sizeof($titles);
    for ($i=0;$i<sizeof($titles);$i++) {
        //echo $titles[$i]->plaintext;
        $oj=strstr($titles[$i]->plaintext," ",true);
        $oj=replay_hustv_convert($oj);
        $id=trim(strstr($titles[$i]->plaintext," "));
        $tname=problem_get_id_from_virtual($oj,$id);
        if ($tname==null) {
            $res["code"]=1;
            return $res;
        }
        $res["vpid$i"]=$tname;
    }
    $sttime=date("Y-m-d H:i:s",$html->find("#overview tr",1)->find("td",1)->plaintext/1000);
    $edtime=date("Y-m-d H:i:s",$html->find("#overview tr",2)->find("td",1)->plaintext/1000);
    if ($html->find("#contest_title img")!=null) {
        $res["code"]=1;
        return $res;
    }
    $title=trim($html->find("#contest_title",0)->plaintext);
    $res["start_time"]=$sttime;
    $res["end_time"]=$edtime;
    $res["name"]=$title;
    $res["description"]=$res["repurl"]="http://acm.hust.edu.cn/vjudge/data/standing/$cid.json";
    $res["ctype"]="hustvjson";
    $res["code"]=0;
    $res["isvirtual"]=1;

    global $db;
    $row = $db->get_results("select cid from contest where description = '".$db->escape($res["description"])."'", ARRAY_N);
    if ($db->num_rows) {
        $rcid=$row[0][0];
        $res["msg"]="Contest $cid already exists! CID: <a href='contest_show.php?cid=$rcid' target='_blank'>$rcid</a>";
        $res["code"]=1;
    }

    return $res;
}

function replay_crawl_uestc($cid) {
    $res=array();
    $html=file_get_html("http://acm.uestc.edu.cn/contest.php?cid=$cid");
    if ($html->find("div#login_all")!=null) {
        $res["code"]=1;
        return $res;
    }
    $titles=$html->find("div.list ul");
    for ($i=0;$i<sizeof($titles);$i++) {
        $title=$titles[$i]->find("li a",1);
//        echo $title;
        if ($title==null) continue;
        $tname=problem_get_id_from_oj_and_title("UESTC",trim($title->innertext));
        if ($tname==null) {
            $res["code"]=1;
            return $res;
        }
        $res["vpid$i"]=$tname;
    }
    $sttime=trim($html->find("#big_title span.h4",0)->plaintext);
    $edtime=trim($html->find("#big_title span.h4",1)->plaintext);
    $title=trim($html->find("#big_title h2",0)->plaintext);
    $res["start_time"]=$sttime;
    $res["end_time"]=$edtime;
    $res["name"]=$title;
    $res["description"]=$res["repurl"]="http://acm.uestc.edu.cn/contest_ranklist.php?cid=$cid";
    $res["ctype"]="uestc";
    $res["code"]=0;
    $res["isvirtual"]=0;
    return $res;
}

function replay_crawl_uva($cid) {
    $res=array();
    $html=file_get_html("http://uva.onlinejudge.org/index.php?option=com_onlinejudge&Itemid=13&page=show_contest&contest=$cid");
    if ($html->find("h1")!=null) {
        $res["code"]=1;
        return $res;
    }
    $titles=$html->find("div.tabbertab table tr");
    for ($i=1;$i<sizeof($titles);$i++) {
        $title=$titles[$i]->find("td",1);
//        echo $title;
        if ($title==null) continue;
        $tname=problem_get_id_from_oj_and_title("UVA",trim($title->innertext));
        if ($tname==null) {
            $res["code"]=1;
            return $res;
        }
        $res["vpid".($i-1)]=$tname;
    }
    $sttime="";
    $edtime="";
    $title=trim($html->find("div.componentheading",0)->plaintext);
    $res["start_time"]=$sttime;
    $res["end_time"]=$edtime;
    $res["name"]=$title;
    $res["description"]=$res["repurl"]="http://uva.onlinejudge.org/index2.php?option=com_onlinejudge&Itemid=13&page=show_contest_standings&contest=$cid";
    $res["ctype"]="uva";
    $res["code"]=0;
    $res["isvirtual"]=0;
    return $res;
}

function replay_crawl_openjudge($cid) {
    global $config;
    $res=array();
    $html=file_get_html("http://poj.openjudge.cn/$cid/");
    if ($html->find("div#error")!=null) {
        $res["code"]=1;
        return $res;
    }
    $titles=$html->find("table td.title a");
    for ($i=0;$i<sizeof($titles);$i++) {
        $title=trim($titles[$i]->innertext);
        if ($title==null) continue;
        $tname=problem_get_id_from_oj_and_title("OpenJudge",$title);
        if ($tname==null) {
            $res["code"]=1;
            return $res;
        }
        $res["vpid".$i]=$tname;
    }
    $sttime=trim($html->find("dd.start-time-dd",0)->plaintext);
    $edtime=trim($html->find("dd.end-time-dd",0)->plaintext);
    $title=trim($html->find("h2",1)->plaintext);
    $res["start_time"]=$sttime;
    $res["end_time"]=$edtime;
    $res["name"]=$title;
    $res["description"]="http://poj.openjudge.cn/$cid/ranking";
    $res["repurl"]=$config["base_url"]."ajax/openjudge_standing_merge.php?contest=".$cid;
    $res["ctype"]="openjudge";
    $res["code"]=0;
    $res["isvirtual"]=0;
    return $res;
}

function replay_crawl_scu($cid) {
    $res=array();
    $html=file_get_html("http://cstest.scu.edu.cn/soj/contest/contest.action?cid=$cid");
    if ($html->find("table",0)==null) {
        $res["code"]=1;
        return $res;
    }
    $titles=$html->find("table",1)->find("tr");
    for ($i=1;$i<sizeof($titles)-1;$i++) {
        $title=$titles[$i]->find("td a",0);
        //echo $title;
        if ($title==null) continue;
        $tname=problem_get_id_from_oj_and_title("SCU",trim(iconv("GBK","UTF-8//IGNORE",$title->innertext)));
        if ($tname==null) {
            $res["code"]=1;
            return $res;
        }
        $res["vpid".($i-1)]=$tname;
    }
    $sttime="";
    $edtime=trim($html->find("table",0)->find("td",2)->plaintext);
    $edtime=strstr(substr($edtime,strpos($edtime,"at ")+3),".",true).":00";
    $title=trim($html->find("h1",0)->plaintext);
    $res["start_time"]=$sttime;
    $res["end_time"]=$edtime;
    $res["name"]=$title;
    $res["description"]=$res["repurl"]="http://cstest.scu.edu.cn/soj/contest/rank.action?cid=$cid";
    $res["ctype"]="scu";
    $res["code"]=0;
    $res["isvirtual"]=0;
    return $res;
}

function replay_crawl_hust($cid) {
    $res=array();
    $html=file_get_html("http://acm.hust.edu.cn/contest.php?cid=$cid");
    if ($html->find("table",1)==null) {
        $res["code"]=1;
        return $res;
    }
    $titles=$html->find("table",1)->find("tr");
    for ($i=1;$i<sizeof($titles);$i++) {
        $title=$titles[$i]->find("td",1)->plaintext;
        //echo $title;
        if ($title==null) continue;
        $tname=problem_get_id_from_virtual("HUST",trim(strstr($title," ",true)));
        if ($tname==null) {
            $res["code"]=1;
            return $res;
        }
        $res["vpid".($i-1)]=$tname;
    }
    preg_match('/Start Time: <.*>(.*)</sU',$html,$matches);
    $sttime=$matches[1];
    preg_match('/End Time: <.*>(.*)</sU',$html,$matches);
    $edtime=$matches[1];
    $title=trim($html->find("h3",0)->plaintext);
    $res["start_time"]=$sttime;
    $res["end_time"]=$edtime;
    $res["name"]=$title;
    $res["description"]=$res["repurl"]="http://acm.hust.edu.cn/contestrank.php?cid=$cid";
    $res["ctype"]="hust";
    $res["code"]=0;
    $res["isvirtual"]=0;
    return $res;
}

?>

