<?php
/**
 * Created by PhpStorm.
 * User: caipeichao
 * Date: 14-3-8
 * Time: PM4:14
 */

namespace Forum\Model;
use Think\Model;

class ForumPostModel extends Model {
    protected $_validate = array(
        array('title', '1,100', '标题长度不合法', self::EXISTS_VALIDATE, 'length'),
        array('content', '1,40000', '内容长度不合法', self::EXISTS_VALIDATE, 'length'),
    );

    protected $_auto = array(
        array('create_time', NOW_TIME, self::MODEL_INSERT),
        array('update_time', NOW_TIME, self::MODEL_BOTH),
        array('last_reply_time', NOW_TIME, self::MODEL_INSERT),
        array('status', '1', self::MODEL_INSERT),
    );

    public function editPost($data) {
        $at_source=$data['content'];

        $data = $this->create($data);
        if(!$data) return false;
        // 对@进行处理

        $content = $this->filterPostContent($data['content']);
        $data['content']=$content;
        $data['title']=op_t($data['title']);
        $this->handlerAt($at_source,$data['id']);
        return $this->save($data);
    }

    public function createPost($data) {
        //新增帖子
        $at_source=$data['content'];
        $data = $this->create($data);

        //对帖子内容进行安全过滤
        if(!$data) return false;




        $content = $this->filterPostContent($data['content']);
        $data['content']=$content;
        $data['title']=op_t($data['title']);
        $result = $this->add($data);
        action_log('add_post','ForumPost',$result,is_login());
        if(!$result) {
            return false;
        }

        //增加板块的帖子数量
        D('Forum')->where(array('id'=>$data['forum_id']))->setInc('post_count');
        $this->handlerAt($at_source,$result);
        //返回帖子编号
        return $result;
    }
// mymymymy
    public function createPosts($data,$uid) {
        //新增帖子
        $at_source=$data['content'];
        $data = $this->create($data);

        //对帖子内容进行安全过滤
        if(!$data) return false;




        $content = $this->filterPostContent($data['content']);
        $data['content']=$content;
        $data['title']=op_t($data['title']);
        $result = $this->add($data);
        action_log('add_post','ForumPost',$result,$uid);
        if(!$result) {
            return false;
        }

        //增加板块的帖子数量
        D('Forum')->where(array('id'=>$data['forum_id']))->setInc('post_count');
        $this->handlerAt($at_source,$result);
        //返回帖子编号
        return $result;
    }

    /**
     * @param $data
     * @auth 陈一枭
     */
    private function handlerAt($content,$id)
    {
        D('ContentHandler')->handleAtWho($content,U('Forum/Index/detail',array('id'=>$id)));

    }

    private function filterPostContent($content)
    {
        $content = op_h($content);
        $content = $this->limitPictureCount($content);
        $content = op_h($content);
        return $content;
    }
    private function limitPictureCount($content)
    {
        //默认最多显示10张图片
        $maxImageCount = 10;

        //正则表达式配置
        $beginMark = 'BEGIN0000hfuidafoidsjfiadosj';
        $endMark = 'END0000fjidoajfdsiofjdiofjasid';
        $imageRegex = '/<img(.*?)\\>/i';
        $reverseRegex = "/{$beginMark}(.*?){$endMark}/i";

        //如果图片数量不够多，那就不用额外处理了。
        $imageCount = preg_match_all($imageRegex, $content);
        if ($imageCount <= $maxImageCount) {
            return $content;
        }

        //清除伪造图片
        $content = preg_replace($reverseRegex, "<img$1>", $content);

        //临时替换图片来保留前$maxImageCount张图片
        $content = preg_replace($imageRegex, "{$beginMark}$1{$endMark}", $content, $maxImageCount);

        //替换多余的图片
        $content = preg_replace($imageRegex, "[图片]", $content);

        //将替换的东西替换回来
        $content = preg_replace($reverseRegex, "<img$1>", $content);

        //返回结果
        return $content;
    }
/**
*获取置顶的帖子
*@param    $id   板块id
*@return   array
*/
    public function getTop($id='',$limit=5){
        if(empty($id)) return '';
        $map['is_top']   = 2;
        $map['forum_id'] = $id;
        $map['status']   = 1;
        $data = $this->where($map)->limit($limit)->select();
        return $data?$data:'';
    }
/**
*获取发帖人的id
**/
    public function getPostUid($post_id){
        $map['id'] = $post_id;
        $uid = $this->where($map)->getField('uid');
        return $uid;
    }
/**
 * 后台删除评论的时候同时删除评论条数
 * @param  array  $id  评论表里的评论id
 */
    public function delReplt($id){
        $map['id'] = array('in', $id);
        $postId = D('ForumPostReply')->where($map)->getField('post_id',true);
        foreach ($postId as $val) {
            $replyCount = $this->where("id={$val}")->getField('reply_count');
            if($replyCount > 0){
                $this->where("id={$val}")->setDec('reply_count');
            }
        }
    }

}