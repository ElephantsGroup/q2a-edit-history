<?php
/*
	Question2Answer Edit History plugin
	License: http://www.gnu.org/licenses/gpl.html
*/

class qa_html_theme_layer extends qa_html_theme_base
{
	private $rev_postids = array();

	function doctype()
	{
		if($this->request == 'admin/permissions' && qa_get_logged_in_level()>=QA_USER_LEVEL_ADMIN) {
			$permits[] = 'permit_view_revisions';
			foreach($permits as $optionname) {
				$value = qa_opt($optionname);
				$optionfield=array(
					'id' => $optionname,
					'label' => qa_lang_html('edithistory/'.$optionname).':',
					'tags' => 'NAME="option_'.$optionname.'" ID="option_'.$optionname.'"',
					'error' => qa_html(@$errors[$optionname]),
				);
				
				$permitoptions=qa_admin_permit_options(QA_PERMIT_ALL, QA_PERMIT_ADMINS, (!QA_FINAL_EXTERNAL_USERS) && qa_opt('confirm_user_emails'));
				
				if (count($permitoptions)>1)
					qa_optionfield_make_select($optionfield, $permitoptions, $value,
						($value==QA_PERMIT_CONFIRMED) ? QA_PERMIT_USERS : min(array_keys($permitoptions)));
				$this->content['form']['fields'][$optionname]=$optionfield;

				$this->content['form']['fields'][$optionname.'_points']= array(
					'id' => $optionname.'_points',
					'tags' => 'NAME="option_'.$optionname.'_points" ID="option_'.$optionname.'_points"',
					'type'=>'number',
					'value'=>qa_opt($optionname.'_points'),
					'prefix'=>qa_lang_html('admin/users_must_have').'&nbsp;',
					'note'=>qa_lang_html('admin/points')
				);
				$checkboxtodisplay[$optionname.'_points']='(option_'.$optionname.'=='.qa_js(QA_PERMIT_POINTS).') ||(option_'.$optionname.'=='.qa_js(QA_PERMIT_POINTS_CONFIRMED).')';
			}
			qa_set_display_rules($this->content, $checkboxtodisplay);
		}

		$q_tmpl = $this->template == 'question';
		$qa_exists = isset($this->content['q_view']) && isset($this->content['a_list']);

		$user_permit = !qa_user_permit_error('edit_history_view_permission');
		
		if ( $q_tmpl && $qa_exists && $user_permit )
		{
			if(@$this->content['q_view']['form']['buttons']['edit']['tags'])
				$this->content['q_view']['form']['buttons']['edit']['tags'] .= ' onclick="return edit_check(' . $this->content['q_view']['raw']['postid'] . ');"';

			// grab a list of all Q/A posts on this page
			$postids = array( $this->content['q_view']['raw']['postid'] );
			foreach ( $this->content['a_list']['as'] as $key=>$answ )
			{
				if(@$this->content['a_list']['as'][$key]['form']['buttons']['edit']['tags'])
				{
					$this->content['a_list']['as'][$key]['form']['buttons']['edit']['tags'] .= ' onclick="return edit_check(' . $answ['raw']['postid'] . ');"';
					$postids[] = $answ['raw']['postid'];
				}
			}

			$sql = 'SELECT postid, MAX(UNIX_TIMESTAMP(updated)) AS last_update FROM ^edit_history WHERE postid IN (' . implode(', ', $postids) . ') GROUP BY postid';
			$result = qa_db_read_all_assoc( qa_db_query_sub($sql) );
			foreach($result as $row)
				$this->rev_postids[$row['postid']] = $row['last_update'];
		}

		parent::doctype();
	}

	function head_script()
	{		
		$json_data = '{';
		foreach($this->rev_postids as $postid=>$date)
			$json_data .= "'$postid':'$date',";
		$json_data .= '}';
		
		$this->output_raw('<script type="text/javascript">');
		$this->output_raw("var revisions = $json_data;");
		$this->output_raw("var lock_time = " . qa_opt('edit_history_NET') . ";");
		$this->output_raw('var edit_check = function(postid){');
		$this->output_raw('var now = new Date().getTime()/1000;');
		$this->output_raw('if(Math.round(now)-revisions[postid]<lock_time){');
		$this->output(
			'var id = "#meta-message-" + postid;',
			'var msg = $(id);',
			'msg.show(500);',
			'setTimeout(function(){msg.hide(500);},5000);',
			'msg.html("' . qa_lang_html_sub('edithistory/edit_locked', qa_opt('edit_history_NET')) . '");'
		);
		$this->output_raw('return false;');
		$this->output_raw('}');
		$this->output_raw('return true;');
		$this->output_raw('}');
		$this->output_raw('</script>');

		parent::head_script();
	}

	function post_meta($post, $class, $prefix=null, $separator='<br />')
	{
		// only link when there are actual revisions
		if ( isset($post['when_2']) && in_array( $post['raw']['postid'], array_keys($this->rev_postids) ) )
		{
			$url = qa_path_html("revisions", array("qa_1"=>$post['raw']['postid']));
			$post['when_2']['data'] = '<a rel="nofollow" href="'.$url.'" class="'.$class.'-revised">' . $post['when_2']['data'] . '</a>';
		}

		parent::post_meta($post, $class, $prefix, $separator);
	}
	function post_avatar_meta($post, $class, $avatarprefix=null, $metaprefix=null, $metaseparator='<br />')
	{
		$this->output('<span class="'.$class.'-avatar-meta">');
		$this->post_avatar($post, $class, $avatarprefix);
		$this->post_meta($post, $class, $metaprefix, $metaseparator);
		$this->output('<span class="meta-message" id="meta-message-' . $post['raw']['postid'] . '" style="color: red; font-size; 10px; display: none;">');
		$this->output('</span>');
		$this->output('</span>');
	}

}
