<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2012 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: yangweijie <yangweijiester@gmail.com> <code-tech.diandian.com>
// +----------------------------------------------------------------------

/**
 * 扩展后台管理页面
 * @author yangweijie <yangweijiester@gmail.com>
 */

class AddonsController extends AdminController {

    static protected $nodes = array(
        array(
            'title'=>'插件管理', 'url'=>'Addons/index', 'group'=>'扩展',
            'operator'=>array(
                //权限管理页面的五种按钮
                array('title'=>'创建','url'=>'Addons/create'),
                array('title'=>'检测创建','url'=>'Addons/checkForm'),
                array('title'=>'弹窗','url'=>'Addons/window'),
                array('title'=>'设置','url'=>'Addons/config'),
                array('title'=>'禁用','url'=>'Addons/disable'),
                array('title'=>'启用','url'=>'Addons/enable'),
                array('title'=>'安装','url'=>'Addons/install'),
                array('title'=>'卸载','url'=>'Addons/uninstall'),
                array('title'=>'更新配置','url'=>'Addons/saveconfig'),
                array('title'=>'插件后台列表','url'=>'Addons/adminList')
            ),
        ),
        array( 'title'=>'钩子管理', 'url'=>'Addons/hooks', 'group'=>'扩展',
            'operator'=>array(
            //权限管理页面的五种按钮
                array('title'=>'编辑','url'=>'Addons/updateSort'),
            ),
        ),
    );

    public function _initialize(){
        $this->assign('_extra_menu',array(
            '已装插件后台'=>D('Addons')->getAdminList(),
        ));
        parent::_initialize();
    }

    //创建向导首页
    public function create(){
        $hooks = include 'hooks_config.php';
        $this->assign('Hooks',$hooks);
        $this->assign('lisence_info','插件创建向导0.1');
        $this->assign('theme','ambiance');//还可以是monokai代码预览的高亮主题
        $this->assign('url_path',$this->url.'/html/');
        $this->display('create');
    }

    public function checkForm(){
        $this->success('好的');
    }

    /**
     * 插件列表
     */
    public function index(){
        $this->assign('list',D('Addons')->getList());
        $this->display();
    }

    /**
     * 插件后台显示页面
     * @param string $name 插件名
     */
    public function adminList($name){
        $addon = addons($name);
        if(!$addon)
            $this->error('插件不存在');
        $param = $addon->admin_list;
        if(!$param)
            $this->error('插件列表信息不正确');
        extract($param);
        $this->assign('title', $addon->info['title']);
        if($addon->custom_adminlist)
            $this->assign('custom_adminlist', $addon->addon_path.$addon->custom_adminlist);
        $this->assign($param);
        $list = $this->lists(D("Addons://{$model}/{$model}")->field($fields),$map,$order);
        $this->assign('list', $list);
        $this->display();
    }

    /**
     * 启用插件
     */
    public function enable(){
        $id = I('id');
        $msg = array('success'=>'启用成功', 'error'=>'启用失败');
        $this->resume('Addons', "id={$id}", $msg);
    }

    /**
     * 禁用插件
     */
    public function disable(){
        $id = I('id');
        $msg = array('success'=>'禁用成功', 'error'=>'禁用失败');
        $this->forbid('Addons', "id={$id}", $msg);
    }

    /**
     * 设置插件页面
     */
    public function config(){
        $id = (int)I('id');
        $addon = D('Addons')->find($id);
        if(!$addon)
            $this->error('插件未安装');
        $this->assign('data',$addon);
        if($addon['custom_config'])
            $this->assign('custom_config', $addon['addon_path'].$addon['custom_config']);
        $this->display();
    }

    /**
     * 保存插件设置
     */
    public function saveConfig(){
        $id = (int)I('id');
        $config = I('config');
        $flag = D('Addons')->where("id={$id}")->setField('config',json_encode($config));
        if($flag !== false){
            $this->success('保存成功');
        }else{
            $this->error('保存失败');
        }
    }

    /**
     * 安装插件
     */
    public function install(){
    	$addons = addons(trim(I('addon_name')));
    	if(!$addons)
    		$this->error('插件不存在');
		$info = $addons->info;
		if(!$info || !$addons->checkInfo())//检测信息的正确性
			$this->error('插件信息缺失');
		$install_flag = $addons->install();
		if(!$install_flag)
			$this->error('执行插件预安装操作失败');

		$addonsModel = D('Addons');
		$data = $addonsModel->create($info);
		if(!$data)
			$this->error($addonsModel->getError());
		if($addonsModel->add()){
            if($hooks_update = D('Hooks')->updateHooks($addons->getName())){
                S('hooks', null);
                $this->success('安装成功');
            }else{
                $this->error('更新钩子处插件失败,请卸载后尝试重新安装');
            }

		}else{
			$this->error('写入插件数据失败');
		}
    }

    /**
     * 卸载插件
     */
    public function uninstall(){
    	$addonsModel = D('Addons');
    	$id = trim(I('id'));
    	$db_addons = $addonsModel->find($id);
    	$addons = addons($db_addons['name']);
        $this->assign('jumpUrl',U('index'));
    	if(!$db_addons || !$addons)
    		$this->error('插件不存在');
    	$uninstall_flag = $addons->uninstall();
		if(!$uninstall_flag)
			$this->error('执行插件预卸载操作失败');
        $hooks_update = D('Hooks')->removeHooks($addons->getName());
        if($hooks_update === false){
            $this->error('卸载插件所挂载的钩子数据失败');
        }
        S('hooks', null);
		$delete = $addonsModel->delete($id);
		if($delete === false){
			$this->error('卸载插件失败');
		}else{
			$this->success('卸载成功');
		}
    }

    /**
     * 钩子列表
     */
    public function hooks(){
        $order = $field = array();
        $this->assign('list', D('Hooks')->field($field)->order($order)->select());
        $this->display();
    }

    public function updateSort(){
        $addons = trim(I('addons'));
        $id = I('id');
        D('Hooks')->where("id={$id}")->setField('addons', $addons);
        S('hooks', null);//:TODO S方法更新缓存 前后台不一致，有BUG
        $this->success('更新成功');
    }

    public function execute($_addons = null, $_controller = null, $_action = null){
        if(C('URL_CASE_INSENSITIVE')){
            $_addons = ucfirst(strtolower($_addons));
            $_controller = parse_name($_controller,1);
        }

        if(!empty($_addons) && !empty($_controller) && !empty($_action)){
            $Addons = A("Addons://{$_addons}/{$_controller}")->setName($_addons)->$_action();
        } else {
            $this->error('没有指定插件名称，控制器或操作！');
        }
    }

    /**
     * 设置当前插件名称
     * @param string $name 插件名称
     */
    protected function setName($name){
        $this->addons = $name;
        return $this;
    }
}
