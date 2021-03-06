<?php
/**
 * Created by PhpStorm.
 * User: caipeichao
 * Date: 14-3-10
 * Time: PM9:14
 */

namespace Weibo\Controller;

use Think\Controller;
use Think\Hook;
use Weibo\Api\WeiboApi;
use Think\Exception;
use Common\Exception\ApiException;

class IndexController extends Controller
{
    /**
     * 业务逻辑都放在 WeiboApi 中
     * @var
     */
    private $weiboApi;

    public function _initialize()
    {
        $this->weiboApi = new WeiboApi();
    }

    public function index($uid = 0, $page = 1, $lastId = 0)
    {
        $page=intval($page);
        //载入第一页微博
        if ($uid != 0) {
            $result = $this->weiboApi->listAllWeibo($page, 30, array('uid' => $uid), 1, $lastId);
        } else {
            $result = $this->weiboApi->listAllWeibo($page, 30, '', 1, $lastId);
        }
        //显示页面
        $this->assign('list', $result['list']);
        $this->assign('lastId', $result['lastId']);
        $this->assign('page', $page);
        $this->assign('tab', 'all');
        $this->assign('loadMoreUrl', U('loadweibo', array('uid' => $uid)));
        $total_count = $this->weiboApi->listAllWeiboCount();

        $this->assign('total_count', $total_count['total_count']);

        $this->assign('tox_money_name', getToxMoneyName());
        $this->assign('tox_money', getMyToxMoney());
        $this->setTitle('全站关注——微博');
        $this->assign('filter_tab', '全站动态');
        $this->assignSelf();
        $this->display();
    }

    public function search($uid = 0, $page = 1, $lastId = 0)
    {
        $keywords = op_t($_REQUEST['keywords']);
        if (!isset($keywords)) {
            $keywords = '';
        }
        //载入第一页微博
        if ($uid != 0) {
            $result = $this->weiboApi->listAllWeibo($page, null, array('uid' => $uid), 1, $lastId, $keywords);
        } else {
            $result = $this->weiboApi->listAllWeibo($page, 0, '', 1, $lastId, $keywords);
        }
        //显示页面
        $this->assign('list', $result['list']);
        $this->assign('lastId', $result['lastId']);
        $this->assign('page', $page);
        $this->assign('tab', 'all');
        $this->assign('loadMoreUrl', U('loadWeibo', array('uid' => $uid, 'keywords' => $keywords)));
        if (isset($keywords) && $keywords != '') {
            $map['content'] = array('like', "%{$keywords}%");
        }
        $total_count = $this->weiboApi->listAllWeiboCount($map);

        $this->assign('key_words', $keywords);
        $this->assign('total_count', $total_count['total_count']);

        $this->assign('tox_money_name', getToxMoneyName());
        $this->assign('tox_money', getMyToxMoney());
        $this->setTitle('全站搜索微博');
        $this->assign('filter_tab', '全站动态');
        $this->assignSelf();
        $this->display();
    }

    public function myconcerned($uid = 0, $page = 1, $lastId = 0)
    {
        if ($page == 1) {
            $result = $this->weiboApi->listMyFollowingWeibo($page, null, '', 1, $lastId);
            $this->assign('lastId', $result['lastId']);
            $this->assign('list', $result['list']);
        }
        //载入我关注的微博


        $total_count = $this->weiboApi->listMyFollowingWeiboCount($page, 0, '', 1, $lastId);
        $this->assign('total_count', $total_count['total_count']);

        $this->assign('page', $page);

        //显示页面


        $this->assign('tab', 'concerned');
        $this->assign('loadMoreUrl', U('loadConcernedWeibo'));

        $this->assignSelf();
        $this->setTitle('我关注的——微博');
        $this->assign('filter_tab', '我关注的');


        $this->assign('tox_money_name', getToxMoneyName());
        $this->assign('tox_money', getMyToxMoney());
        $this->display('index');
    }

    public function weiboDetail($id)
    {
        //读取微博详情
        $result = $this->weiboApi->getWeiboDetail($id);

        //显示页面
        $this->assign('weibo', $result['weibo']);
        $this->assignSelf();
        $this->setTitle('{$weibo.content|op_t}——微博详情');

        $this->display();
    }

    public function sendrepost($sourseId, $weiboId)
    {


        $result = $this->weiboApi->getWeiboDetail($sourseId);
        $this->assign('soueseWeibo', $result['weibo']);

        if ($sourseId != $weiboId) {
            $weibo1 = $this->weiboApi->getWeiboDetail($weiboId);
            $weiboContent = '//@' . $weibo1['weibo']['user']['nickname'] . ' ：' . $weibo1['weibo']['content'];

        }
        $this->assign('weiboId', $weiboId);
        $this->assign('weiboContent', $weiboContent);
        $this->assign('sourseId', $sourseId);

        $this->display();
    }

    public function doSendRepost($content, $type, $sourseId, $weiboId, $becomment)
    {
        $feed_data = '';
        $sourse = $this->weiboApi->getWeiboDetail($sourseId);
        $sourseweibo = $sourse['weibo'];
        $feed_data['sourse'] = $sourseweibo;
        $feed_data['sourseId'] = $sourseId;

        Hook('beforeSendRepost', array('content' => &$content, 'type' => &$type, 'feed_data' => &$feed_data));

        //发送微博
        $result = $this->weiboApi->sendWeibo($content, $type, $feed_data);
        if ($result) {
            D('weibo')->where('id=' . $sourseId)->setInc('repost_count');
            $weiboId != $sourseId && D('weibo')->where('id=' . $weiboId)->setInc('repost_count');
            S('weibo_' . $weiboId, null);
            S('weibo_' . $sourseId, null);
        }

        $user = query_user(array('nickname'), is_login());
        $toUid = D('weibo')->where(array('id' => $weiboId))->getField('uid');
        D('Common/Message')->sendMessage($toUid, $user['nickname'] . '转发了您的微博！', '转发提醒', U('Weibo/Index/weiboDetail', array('id' => $result['weibo_id'])), is_login(), 1);


        if ($becomment == 'true') {
            $this->weiboApi->sendRepostComment($weiboId, $content);
        }

        $weibo = $this->weiboApi->getWeiboDetail($result['weibo_id']);

        $result['html']=R('WeiboDetail/weibo_html',array('weibo'=>$weibo['weibo']),'Widget');
        //返回成功结果
        $this->ajaxReturn(apiToAjax($result));
    }

    public function loadweibo($page = 1, $uid = 0, $loadCount = 1, $lastId = 0, $keywords = '')
    {
        $count = 30;
        //载入全站微博
        if ($uid != 0) {
            $result = $this->weiboApi->listAllWeibo($page, $count, array('uid' => $uid), $loadCount, $lastId, $keywords);
        } else {
            $result = $this->weiboApi->listAllWeibo($page, $count, '', $loadCount, $lastId, $keywords);
        }
        //如果没有微博，则返回错误
        if (!$result['list']) {
            $this->error('没有更多了');
        }

        //返回html代码用于ajax显示
        $this->assign('list', $result['list']);
        $this->assign('lastId', $result['lastId']);
        $this->display();
    }

    public function loadConcernedWeibo($page = 1, $loadCount = 1, $lastId = 0)
    {

        $count = 30;
        //载入我关注的人的微博
        $result = $this->weiboApi->listMyFollowingWeibo($page, $count, '', $loadCount, $lastId);

        //如果没有微博，则返回错误
        if (!$result['list']) {
            $this->error('没有更多了');
        }

        //返回html代码用于ajax显示
        $this->assign('list', $result['list']);
        $this->assign('lastId', $result['lastId']);
        $this->display('loadweibo');
    }

    public function doSend($content, $type = 'feed', $attach_ids = '')
    {
        $feed_data = '';
        $feed_data['attach_ids'] = $attach_ids;

        Hook('beforeSendWeibo', array('content' => &$content, 'type' => &$type, 'feed_data' => &$feed_data));

        //发送微博
        $result = $this->weiboApi->sendWeibo($content, $type, $feed_data);

        $weibo = $this->weiboApi->getWeiboDetail($result['weibo_id']);

        $result['html']=R('WeiboDetail/weibo_html',array('weibo'=>$weibo['weibo']),'Widget');

        //返回成功结果
        $this->ajaxReturn(apiToAjax($result));
    }

    public function settop($weibo_id)
    {
        $weibo_id = intval($weibo_id);
        if (!is_administrator()) {
            $this->error('置顶失败，您不具备管理权限。');
        } else {
            $weiboModel = D('Weibo');
            $weibo = $weiboModel->find($weibo_id);
            if (!$weibo) {
                $this->error('置顶失败，微博不能存在。');
            }
            if ($weibo['is_top'] == 0) {
                if ($weiboModel->where(array('id' => $weibo_id))->setField('is_top', 1)) {
                    S('weibo_' . $weibo_id, null);
                    $this->success('置顶成功。');
                } else {
                    $this->error('置顶失败。');
                };
            } else {
                if ($weiboModel->where(array('id' => $weibo_id))->setField('is_top', 0)) {
                    S('weibo_' . $weibo_id, null);
                    $this->success('取消置顶成功。');
                } else {
                    $this->error('取消置顶失败。');
                };
            }


        }
    }

    public function doComment($weibo_id, $content, $comment_id = 0)
    {
        //发送评论
        $result = $this->weiboApi->sendComment($weibo_id, $content, $comment_id);

        //返回成功结果
        $this->ajaxReturn(apiToAjax($result));
    }

    public function loadComment($weibo_id)
    {
        //读取数据库中全部的评论列表
        // $result = $this->weiboApi->listComment($weibo_id, 1, 10000);
        //$list = $result['list'];
        $weiboCommentTotalCount = D('WeiboComment')->where(array('weibo_id' => intval($weibo_id), 'status' => 1))->count();
        //$weiboCommentTotalCount = count($list);

        $result1 = $this->weiboApi->listComment($weibo_id, 1, 5);
        $list1 = $result1['list'];
        //返回html代码用于ajax显示
        $this->assign('list', $list1);
        $this->assign('weiboId', $weibo_id);
        $weobo = $this->weiboApi->getWeiboDetail($weibo_id);
        $this->assign('weibo', $weobo['weibo']);
        $this->assign('weiboCommentTotalCount', $weiboCommentTotalCount);
        $this->display();
    }

    public function commentlist($weibo_id, $page = 1)
    {

        $result = $this->weiboApi->listComment($weibo_id, $page, 10000);
        $list = $result['list'];
        $this->assign('list', $list);
        $this->assign('weiboId', $weibo_id);
        $html = $this->fetch('commentlist');
        $this->ajaxReturn($html);
        dump($html);

    }

    public function doDelWeibo($weibo_id = 0)
    {
        //删除微博
        $result = $this->weiboApi->deleteWeibo($weibo_id);

        //返回成功信息
        $this->ajaxReturn(apiToAjax($result));
    }

    public function doDelComment($comment_id = 0)
    {
        //删除评论
        $result = $this->weiboApi->deleteComment($comment_id);

        //返回成功信息
        $this->ajaxReturn(apiToAjax($result));
    }

    public function atWhoJson()
    {
        exit(json_encode($this->getAtWhoUsersCached()));
    }

    /**
     * 获取表情列表。
     */
    public function getSmile()
    {
        //这段代码不是测试代码，请勿删除
        exit(json_encode(D('Expression')->getAllExpression()));
    }

    private function getAtWhoUsers()
    {
        //获取能AT的人，UID列表
        $uid = get_uid();
        $follows = D('Follow')->where(array('who_follow' => $uid, 'follow_who' => $uid, '_logic' => 'or'))->limit(999)->select();
        $uids = array();
        foreach ($follows as &$e) {
            $uids[] = $e['who_follow'];
            $uids[] = $e['follow_who'];
        }
        unset($e);
        $uids = array_unique($uids);

        //加入拼音检索
        $users = array();
        foreach ($uids as $uid) {
            $user = query_user(array('nickname', 'id', 'avatar32'), $uid);
            $user['search_key'] = $user['nickname'] . D('PinYin')->Pinyin($user['nickname']);
            $users[] = $user;
        }

        //返回at用户列表
        return $users;
    }

    private function getAtWhoUsersCached()
    {
        $cacheKey = 'weibo_at_who_users_' . get_uid();
        $atusers = S($cacheKey);
        if (empty($atusers)) {
            $atusers = $this->getAtWhoUsers();
            S($cacheKey, $atusers, 600);
        }
        return $atusers;
    }

    private function assignSelf()
    {
        $self = query_user(array('title','avatar128', 'nickname', 'uid', 'space_url', 'icons_html', 'score', 'title', 'fans', 'following', 'weibocount', 'rank_link'));
        // dump($self);
        $this->assign('self', $self);
    }

/**
我的
*/
    public function myPaihang(){
        // header("content-type:text/html;charset=utf-8");
        $uid = $_REQUEST['uid'];
        if(empty($uid)) exit( json_encode(array('msg'=>'error','ret'=>100,'data'=>'')));
        $paihang = query_user(array('title'),$uid);
        $score = M('member')->field('score')->where("uid={$uid}")->find();
        $where = $score['score'];
        $count = M('member')->where("score > {$where}")->count();
        $count = $count+1;
        $data = array(
            'diwei'=>$paihang['title'],
            'paiming'=>$count,
            'nickname'=>$this->getNickname($uid),
            'avatar'=>$this->getAvatar($uid),
            'checkin'=>$this->getCheckin($uid)
        );
        echo json_encode(array('msg'=>'success','ret'=>0,'data'=>$data));
    }
/**
排行（我的排行）
*/
    public function getpaihang(){
        // header("content-type:text/html;charset=utf-8");
        $uid = $_REQUEST['uid'];
        if(!$uid) exit( json_encode(array('msg'=>'error','ret'=>100,'data'=>'')));
        $uScore = M('member')->where("uid={$uid}")->getField("score");
        $result = M('config')->field("value")->where('name="_USER_LEVEL"')->limit(999)->select();
        $str = $result[0]['value'];
        $arr = explode("\r\n",$str);
        $name1 = end(explode(':',$arr[0]));
        $name2 = end(explode(':',$arr[1]));
        $name3 = end(explode(':',$arr[2]));
        $name4 = end(explode(':',$arr[3]));
        $name5 = end(explode(':',$arr[4]));
        $name6 = end(explode(':',$arr[5]));
        $name7 = end(explode(':',$arr[6]));
        $name8 = end(explode(':',$arr[7]));
        $name9 = end(explode(':',$arr[8]));
        $name10 = end(explode(':',$arr[9]));
        $name11 = end(explode(':',$arr[10]));
        preg_match('/\d{1,5}/',$arr[0],$re1);
        preg_match('/\d{1,5}/',$arr[1],$re2);
        preg_match('/\d{1,5}/',$arr[2],$re3);
        preg_match('/\d{1,5}/',$arr[3],$re4);
        preg_match('/\d{1,5}/',$arr[4],$re5);
        preg_match('/\d{1,5}/',$arr[5],$re6);
        preg_match('/\d{1,5}/',$arr[6],$re7);
        preg_match('/\d{1,5}/',$arr[7],$re8);
        preg_match('/\d{1,5}/',$arr[8],$re9);
        preg_match('/\d{1,5}/',$arr[9],$re10);
        preg_match('/\d{1,5}/',$arr[10],$re11);
        if($uScore == 0){
            $cScore = $re2[0];
            $demo = $name2;
        }else{
            switch ($uScore) {
                case $uScore==0:
                    $cScore = $re2[0];
                    $demo = $name2;
                    break;
                case $uScore<$re2[0]:
                    $cScore = $re2[0] - $uScore;
                    $demo = $name2;
                    break;
                case $uScore<$re3[0]:
                    $cScore = $re3[0] - $uScore;
                    $demo = $name3;
                    break;
                case $uScore<$re4[0]:
                    $cScore = $re4[0] - $uScore;
                    $demo = $name4;
                    break;
                case $uScore<$re5[0]:
                    $cScore = $re5[0] - $uScore;
                    $demo = $name5;
                    break;
                case $uScore<$re6[0]:
                    $cScore = $re6[0] - $uScore;
                    $demo = $name6;
                    break;
                case $uScore<$re7[0]:
                    $cScore = $re7[0] - $uScore;
                    $demo = $name7;
                    break;
                case $uScore<$re8[0]:
                    $cScore = $re8[0] - $uScore;
                    $demo = $name8;
                    break;
                case $uScore<$re9[0]:
                    $cScore = $re9[0] - $uScore;
                    $demo = $name9;
                    break;
                case $uScore<$re10[0]:
                    $cScore = $re10[0] - $uScore;
                    $demo = $name10;
                    break;
                case $uScore<$re11[0]:
                    $cScore = $re11[0] - $uScore;
                    $demo = $name11;
                    break;
                default:
                    $cScore = 0;
                    $demo = "已经是最高地位";
                    break;
            }
        }
        $paihang = query_user(array('title'),$uid);
        $score = M('member')->field('score')->where("uid={$uid}")->find();
        $where = $score['score'];
        $count = M('member')->where("score > {$where}")->count();
        $count = $count+1;
        $data = array(
            'diwei'=>$paihang['title'],
            'paiming'=>$count,
            'cScore'=>$cScore,
            'score' => $uScore,
            'cDiwei'=>$demo
        );

        /*规则*/
        $data['SysScore'] = $this->getScoreDiwei();
        $data['SysRule']  = $this->getScoreRule();
        echo json_encode(array('msg'=>'success','ret'=>0,'data'=>$data));
    }
/**
排行（全国排行）
*/
    public function getpaihangs(){
        $pagenum  = I('pagenum',1);
        $pagesize = I('pagesize',10);
        $uid      = I('uid');
        //$ranking  = S('ranking_'.$uid.'_'.$pagenum);
        //if(!empty($ranking)) exit( $ranking );
        // if(empty($uid)) exit(err(100));
        // $field = array('uid','nickname','score');
        /*获取分组后的总记录数*/
        $sqlCount = "select count(*) c from (select count(*) from thinkox_check_info group by uid) a";
        $re = M()->query($sqlCount);
        $totalCount = $re[0]['c'];

        $userList = D('CheckInfo')->getConNum($pagenum,$pagesize);
        foreach ($userList as &$v) {
            $v['path']     = $this->getAvatar($v['uid']);
            $v['nickname'] = $this->getNickname($v['uid']);
        }
        $totalpage = ceil($totalCount/$pagesize);
        if($pagenum >= $totalpage){
            $hasNextPage = false;
        }else{
            $hasNextPage = true;
        }
        
        $data = array(
            "pagedatas"=>$userList,
            'totalCheckin'=>$this->getCheckin($uid),
            "totalcount"=>$totalCount,
            "pagesize"=>$pagesize,
            "pagenum"=>$pagenum,
            "totalpage"=>$totalpage,
            "hasNextPage"=>$hasNextPage

        );
        // 缓存数据
        //S('ranking_'.$uid.'_'.$pagenum,$data,600);
        echo json_encode(array('msg'=>'success','ret'=>0,'data'=>$data));
    }




/**
*@param uid 用户的id
*@return 用户的头像路径
* 
*/
    protected function getAvatar($uid=''){
        if(empty($uid)){
            return '';
        }else{
            $avatar = M('avatar')->field('path')->where("uid={$uid}")->order("create_time DESC")->find();
            if(empty($avatar['path'])){
                $return = '';
            }else{
                $return = "/Uploads".$avatar['path'];
            }
            return $return;
        }
    }
/**
*@param uid 用户的id
*@return 用户总的签到天数
* 
*/
    protected function getCheckin($uid=''){
        if(empty($uid)){
            return '';
        }else{
           $check = M('Check_info')->field('total_num')->where("uid={$uid}")->order('ctime desc')->find();
           if(empty($check['total_num'])){
                $return = '';
           }else{
                $return = $check['total_num'];
           }
           return $return;
        }
    }
/**
*@param uid 用户的id
*@return 用户的昵称
* 
*/
    public function getNickname($uid){
        if($uid != ''){
            $re = M('member')->field(array('nickname'))->where("uid={$uid}")->find();
            return $re['nickname'];
        }
    }

/**
*@param 以冒号分割的名值对
*@return 得到积分
* 
*/
    public function scores($str){
        $arr = explode(':',$str);
        return $arr[0];
    }
/*获取积分获取的规则*/
    public function getScoreRule(){
        $cache = S('ScoreRule');
        if(!empty($cache)) return $cache;
        $rule = M('action')->field('id,title,remark')->where("type=2 and status=1")->select();
        S('ScoreRule',$rule,600);
        return $rule;
    }
/*获取等级范围*/
    public function getScoreDiwei(){
        $score = M('config')->where("name='_USER_LEVEL'")->getField('value');
        $arr = explode("\r\n",$score);
        $arrs = array();
        $i    = 1;
        foreach ($arr as $v) {
            $arrs[] = array(
                'start' => reset(explode(':',$v)),
                'latt'  => reset(explode(':',$arr[$i])),
                'diwei' => end(explode(':',$v)),
            );
            $i++;
        }
        return $arrs;
    }
    public function ceshi(){
        $re = D('BaiTui')->genDataOne(164,'标题','描述','ceshi');
        dump($re);
    }

}