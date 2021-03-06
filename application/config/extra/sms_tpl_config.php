<?php

/**
 * @Copyright (C), 2016, Liuj.
 * @Name $name
 * @Author Liuj
 * @Version stable 1.0
 * @Date: $date
 * @Description
 * 1. Example
 * @Function List
 * 1.
 * @History
 * Liuj $date     stable 1.0 第一次建
 */
/*
 * 短信模版
 * * */
return [
    'sms_tpl'     => [
        'email_check'         => '标题：尊敬的%s，欢迎来到雅堂金融！请尽快完成邮箱验证！',
        'regester_code'       => '欢迎来到雅堂金融，您的注册验证码:%s。(验证码有效时间为10分钟)',
        'findPassword_code'   => '您正在通过手机方式找回密码，验证码:%s。(验证码有效时间为10分钟)',
        'findrePassword_code' => '您正在通过手机方式找回交易密码，验证码:%s。(验证码有效时间为10分钟)',
        'up_credit_code'      => '您正在申请融资额度，验证码:%s。(验证码有效时间为10分钟)',
        'changePhone_code'    => '您正在修改绑定的手机号码，验证码:%s。(验证码有效时间为10分钟)',
        'cash_code'           => '您正在申请提现，验证码为：%s。(验证码有效时间为10分钟)',
        'addBankCard_code'    => '您正在添加新卡信息，验证码为：%s。(验证码有效时间为10分钟)',
        'repayment'           => '尊敬的%s，您有%s笔融资需要还款，总金额￥%.2f，请于%s之前还清，感谢您的支持！',
        'reciveCollection'    => '尊敬的%s，您收到来自%s的还款，金额为%.2f。感谢您的支持！',
        'recharge'            => '尊敬的%s，您充值的金额￥%.2f已经到账，感谢您的支持！',
        'cash'                => '尊敬的雅堂金融用户，您金额为￥%s的提现申请已经审核成功，即将安排财务打款，请注意查收。',
        'Phone'               => '您正在进行手机短信校验，验证码:%s。(验证码有效时间为10分钟)',
        'borrow_success'      => '尊敬的雅堂金融用户,您的融资 (%s) 已成功融资,融资资金已入账.',
        'worker_loan_success' => '尊敬的%s,恭喜您提交的工薪贷额度申请资料已经通过本站的审核，你获得的初始额度为%.2f元。请务必在次月及以后每月的25日24点前上传你的当月工资流水及话费清单.',
        'worker_loan_fail'    => '尊敬的%s,很遗憾，你提交的工薪贷额度申请资料未通过本站的审核，请你核实自己的申请资料并重新申请.',
        'update_phone'        => '【雅堂金融】您已绑定到新手机%s。如有疑问，致电4000-050-828',
        'login_error'         => '您的后台帐号：%s，正被尝试登录，密码错误次数已超过10次，请注意。',
        'bind_phone'          => '您已绑定到手机%s。',
        'carveout_success'    => '尊敬的用户%s，您申请的创业贷资料审核已经通过！',
        'carveout_faile'      => '尊敬的用户{0}，您申请的创业贷资料审核失败，请整理资料重新申请！',
        'the_prize'           => '尊敬的%s，恭喜您获得%s！'
    ],
    'message_tpl' => [
        'realSuccess'         => array('title' => '恭喜您已完成实名验证，请继续完成其他认证！',
            'body'  => '尊敬的%s，您的实名已完成验证。<br>为了更好的为您服务，您需完成的认证或操作有：<br>1.邮箱认证<br>2.开通VIP服务<br>3.设置交易密码<br>4.添加提现银行卡<br>愿健康与快乐天天伴随您!',
        ),
        'realFail'            => array('title' => '您实名验证失败，请重新完成认证！',
            'body'  => '尊敬的%s，您实名验证失败,失败原因%s。<br>为了更好的为您服务，您需完成的认证或操作有：<br> 1.实名认证 <br> 2.开通VIP服务 <br 3.设置交易密码 <br> 4.添加提现银行卡 <br>愿健康与快乐天天伴随您！<br>',
        ),
        'attSuccess'          => array('title' => '恭喜您已完%s,请继续完成其他认证！',
            'body'  => '尊敬的%s，您已完成实名认证/开通VIP服务/设置交易密码/设置提现银行卡。<br>为了更好的为您服务，您还需完成的认证或操作有(都是链接形式，判断还有那些认证未完成就显示)：<br>1.实名认证<br>2.开通VIP服务<br>3.设置交易密码<br>4.添加提现银行卡<br><br>愿健康与快乐天天伴随您！',
        ),
        'allAttSuccess'       => array('title' => '恭喜您已完成所有认证！',
            'body'  => '尊敬的 %s，恭喜您已完成所有的认证！<br>现在您可以 充值 开始第一次投资之旅，或者 申请额度 开始融资之旅！<br>愿健康与快乐天天伴随您！',
        ),
        'realName'            => array('title' => '%s。',
            'body'  => '尊敬的%s，您的%s期限为%s至%s！<br>为了不影响您的其他操作，请及时 实名认证/续费VIP服务！<br><br>愿健康与快乐天天伴随您！',
        ),
        'credit_submit'       => array('title' => '您的额度申请已提交成功，请上传必要资料。',
            'body'  => '尊敬的%s，您的额度申请已提交成功。<br>为了快速、成功的通过审核，您还需上传以下资料：<br>具体资料由[业务与风控部门]确定<br>[上传资料按钮]<br>愿健康与快乐天天伴随您！',
        ),
//	'repayment' => array('title' => '今天您共有%s笔融资需要还款，总金额￥%s，请于%s24点之前还清。',
//		'body' => '尊敬的%s，今天您共有%s笔融资需要还款，总金额￥%s，请于%s24点之前还清，详细列表如下： <br>
//				 ÓtableÔ
//				 	ÓtrÔÓtdÔ应还日期Ó/tdÔÓtdÔ实还日期Ó/tdÔÓtdÔ期数Ó/tdÔÓtdÔ融资标题Ó/tdÔÓtdÔ还款总额Ó/tdÔÓtdÔ滞纳金Ó/tdÔÓtdÔ催收费用Ó/tdÔÓtdÔ操作/状态Ó/tdÔÓ/trÔ
//				 	%s
//				 ÓtableÔ<br>7天之内还有X笔融资需要还款，总金额￥XXX,XXX.XX，详细情况请至 还款明细 中查看。<br>温馨提示：请您调整好流动资金，避免逾期发生！<br>愿健康与快乐天天伴随您！',
//	),
        'invest_repayment'    => array('title' => '您已收到来自%s的还款，金额为￥%s。',
            'body'  => '尊敬的%s，您已收到来自%s的还款，金额为￥%.2f。详细情况如下：<br>项目的标题：%s<br>实际到期还款日期：%s<br>当前还款为：第%s期/共%s期<br>还款本金：￥%s<br>还款利息：￥%s<br>愿健康与快乐天天伴随您！',
        ),
        'cash'                => array('title' => '您的提现金额￥%s已审核成功，即将安排财务打款，请注意查收。',
            'body'  => '您于%s申请的提现详情如下：<br>提现总金额：￥%s<br>到帐金额：￥%s<br>提现手续费：￥%s<br>抵扣提现手续费：%s<br>实际扣除提现手续费：￥%s<br>您的提现银行卡信息为：<br>开户支行：%s<br>银行账号：%s<br>正常到账时间为1-3个工作日，请及时查收。<br>如有疑问，请拨打我们的客服热线4000 050 828或者联系我们的企业QQ：4000 050 828进行咨询。<br>愿健康与快乐天天伴随您！ ',
        ),
        'birthday'            => array('title' => '雅堂金融祝您生日快乐！',
            'body'  => '亲爱的:%s,今天是您的生日，雅堂金融诚挚祝福您生日快乐！衷心感谢您的支持!<br>愿健康与快乐天天伴随您!',
        ),
        'feast'               => array('title' => '雅堂金融祝您%s节日快乐！',
            'body'  => '尊敬的%s，%s，衷心感谢您的支持！<br>愿健康与快乐天天伴随您！',
        ),
        'invest_success'      => array('title' => '您投资 %s 的项目 %s 已成功！',
            'body'  => '尊敬的%s，您投资 %s 的项目 %s 已成功，总金额:￥%.2f，详细情况请至 收款中的记录 中查看。 ',
        ),
        'invest_false'        => array('title' => '您投资%s的项目 %s已撤消融资。',
            'body'  => '尊敬的%s，您投资 %s 的项目 %s 已撤消融资，金额:￥%.2f,<br>愿健康与快乐天天伴随您！ ',
        ),
        'borrow_success'      => array('title' => '您的融资 (%s) 已成功融资',
            'body'  => '尊敬的%s，您的融资 (%s) 已成功融资,融资资金已入账，金额￥%.2f。<br>请合理安排流动资金，按时还款。<br>愿健康与快乐天天伴随您！',
        ),
        'worker_loan_success' => array('title' => '工薪贷额度申请资料已经通过审核！',
            'body'  => '尊敬的%s，恭喜您提交的工薪贷额度申请资料已经通过本站的审核，你获得的初始额度为%s元。<br>请务必在次月及以后每月的25日24点前上传你的当月工资流水及话费清单。',
        ),
        'worker_loan_fail'    => array('title' => '工薪贷额度申请审核不通过！',
            'body'  => '尊敬的%s，很遗憾，你提交的工薪贷额度申请资料未通过本站的审核，请你核实自己的申请资料并重新申请。',
        ),
        'invite_awards'       => array('title' => '您获得了邀请奖励￥%s',
            'body'  => '尊敬的%s，被邀请者用户名已接受了您的注册邀请并购买了VIP服务%s年，您获得了奖励￥%s。',
        ),
    ],
    'email_tpl'   => [
        'email_check'    => array('title' => '尊敬的{0}，欢迎来到雅堂金融！请尽快完成邮箱验证！',
            'body'  => '尊敬的{0}，欢迎来到雅堂金融！请点击以下链接完成邮箱验证：
			{1}
			(如果链接无法点击，请将它复制到浏览器的地址栏中)
			新手上路秘籍之融资者篇(用图表示最好，都是链接形式)：
			完成邮箱验证 → 完成实名认证 → 充值 → 开通VIP服务 → 申请额度 → 提交材料/现场认证 → 获得额度 → 发布融资 → 成功融资审核通过 → 设置交易密码 → 添加提现银行卡 → 提现 → 按时还款获得信用积分
			新手上路秘籍之投资者篇(用图表示最好，都是链接形式)：
			完成邮箱验证 → 完成实名认证 → 充值 → 开通VIP服务 → 设置交易密码 → 投资 → 添加提现银行卡 → 提现
			愿健康与快乐天天伴随您！
		   ',),
        'emailSuccess'   => array('title' => '恭喜您已完成邮箱验证，请继续完成其他认证！',
            'body'  => '尊敬的{0}，您的邮箱{1}已完成验证。
					为了更好的为您服务，您需完成的认证或操作有(都是链接形式)：
					1.实名认证
					2.开通VIP服务
					3.设置交易密码
					4.添加提现银行卡
					愿健康与快乐天天伴随您！
		   ',
        ),
        'attSuccess'     => array('title' => '完成{0}',
            'body'  => '尊敬的{1}，您已完成{0}。
					为了更好的为您服务，您还需完成的认证或操作有(都是链接形式，判断还有那些认证未完成就显示)：
					1.实名认证
					2.开通VIP服务
					3.设置交易密码
					4.添加提现银行卡
					愿健康与快乐天天伴随您！
		   ',
        ),
        'realName'       => array('title' => '您的{0}',
            'body'  => '尊敬的{1}，您的{0}期限为{2}至{3}！。
					为了不影响您的其他操作，请及时{0}服务！
					愿健康与快乐天天伴随您！
		   ',
        ),
        'credit_submit'  => array('title' => '您的额度申请已提交成功，请上传必要资料。',
            'body'  => '尊敬的{0}，您的额度申请已提交成功。
					为了快速、成功的通过审核，您还需上传以下资料：
					具体资料由(业务与风控部门)确定
					(上传资料按钮)
					愿健康与快乐天天伴随您！
		   ',
        ),
        '6'              => array('title' => '今天您共有{0}笔融资需要还款，总金额￥{1}，请于{2}24点之前还清。',
            'body'  => '尊敬的{2}，今天您共有{0}笔融资需要还款，总金额￥{1}，请于{2}24点之前还清，详细列表如下：(把(还款明细)的列表拿过来)<br>
				 <table>
				 	<tr><td>应还日期</td><td>实还日期</td><td>期数</td><td>融资标题</td><td>还款总额</td><td>滞纳金</td><td>催收费用</td><td>操作/状态</td></tr>
				 	{foreach}<tr><td>{3}{0}</td><td>{3}{1}</td><td>{3}{2}/{3}{3}</td><td>{3}{4}</td><td>{3}{5}</td><td>{3}{6}</td><td>{3}{7}</td><td><a="{8}">操作/状态</a></td></tr>{/froeach}
				 <table><br>7天之内还有X笔融资需要还款，总金额￥XXX,XXX.XX，详细情况请至 还款明细 中查看。<br>温馨提示：请您调整好流动资金，避免逾期发生！<br>愿健康与快乐天天伴随您！
		   ',
        ),
        '7'              => array('title' => '您的额度申请已提交成功，请上传必要资料。',
            'body'  => '尊敬的{0}，您的额度申请已提交成功。
					为了快速、成功的通过审核，您还需上传以下资料：
					具体资料由(业务与风控部门)确定
					(上传资料按钮)
					愿健康与快乐天天伴随您！
		   ',
        ),
        '8'              => array('title' => '您的提现金额￥{0}已经打款，正常到账时间为1-3个工作日，请及时查收。',
            'body'  => '您于{1}申请的提现详情如下：
			提现总金额：￥{2}
			到帐金额：￥{3}
			提现手续费：￥{4}
			抵扣提现手续费：{5}个隆宝
			实际扣除提现手续费：￥{6}
			您的提现银行卡信息为：
			开户支行：{7}
			银行账号：{8}
			正常到账时间为1-3个工作日，请及时查收。
			如有疑问，请拨打我们的客服热线4000 050 828或者联系我们的企业QQ：4000 050 828进行咨询。
			愿健康与快乐天天伴随您！
		   ',
        ),
        '9'              => array('title' => '雅堂金融祝您{0}快乐！',
            'body'  => '(配图片)今天是您的生日，雅堂金融诚挚祝福您生日快乐！衷心感谢您的支持！

					愿健康与快乐天天伴随您！
		   
		   ',
        ),
        '10'             => array('title' => '雅堂金融祝您{0}快乐！',
            'body'  => '展示内容&形式待定

					愿健康与快乐天天伴随您！
		   ',
        ),
        'statistics'     => array('title' => '尊敬的{0}，您的{1}月报统计已产生，请及时查看.',
            'body'  => '展示内容&形式待定

					愿健康与快乐天天伴随您！
		   ',
        ),
        'monthly report' => array('title'   => '雅堂金融2013-05号月报',
            'content' => '展示内容&形式待定
					愿健康与快乐天天伴随您！
		   ',
        ),
    ],
];

