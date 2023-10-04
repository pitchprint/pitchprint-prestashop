{*
* 2023 PitchPrint
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PitchPrint to newer
* versions in the future. If you wish to customize PitchPrint for your
* needs please refer to http://pitchprint.com for more information.
*
*  @author    PitchPrint Inc <hello@pitchprint.com>
*  @copyright 2023 PitchPrint Inc
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PitchPrint Inc.
*}
<div class="product-tab-content">
    <div style="padding: 20px" class="panel product-tab">
        <h3>Assign PitchPrint Design</h3>
        <div class="alert alert-info">
            You can create your designs at <a target="_blank" href="https://admin.pitchprint.io/designs">https://admin.pitchprint.io/designs</a> 
        </div>
        <div id="w2p-div">
            <div style="margin-bottom:10px">
                <select id="ppa_pick" name="ppa_pick" style="width:300px;" class="c-select form-control" value={$current_p_val}>
                    <option style="color:#aaa" value="0">Loading..</option>
                </select>
                <input type="hidden" id="ppa_new_values" name="ppa_new_values" value="{$pp_val}" />
                <input type="hidden" id="pp_indexVal" name="pp_indexVal" value="{$indexval}" />
    
                <select id="ppa_pick_display_mode" name="ppa_pick_display_mode" 
                  style="width:300px;margin-top: 10px" class="c-select form-control" >
                    <option style="color:#aaa" value="">Display Mode</option>
                    <option style="color:#aaa" value="modal">Full Window</option>
                    <option style="color:#aaa" value="inline">Inline</option>
                    <option style="color:#aaa" value="mini">Mini</option>
                </select>
                
                <script>
                (function(){
                	var displayMode = jQuery('[name="ppa_pick_display_mode"]');
                	displayMode.val('{$current_display_opt}');
                	
                	displayMode[0].addEventListener("web2print_options_changed", _updatePpOptions);
                	displayMode.change(_updatePpOptions);
                	
                	function _updatePpOptions () {
                		var ppOptions = jQuery("#ppa_new_values");
                		var a = ppOptions.val().split(":");
                		a[3] = displayMode.val();
                		ppOptions.val(a.join(":"));	 
                	}
                	
                })();
                </script>
            </div>
        <!--<div class="checkbox" style="margin-bottom:10px">
        <label for="ppa_pick_upload"> <input type="checkbox" name="ppa_pick_upload" id="ppa_pick_upload" value="">Enable clients upload their files.</label>
        </div>-->
            <div class="checkbox">
                <label for="ppa_pick_hide_cart_btn"> <input type="checkbox" name="ppa_pick_hide_cart_btn" id="ppa_pick_hide_cart_btn" value="">Required.</label>
            </div>
        </div>
        
        <script type="text/javascript">
				jQuery(function($) {
					{$PPADMIN_DEF}
					PPADMIN.vars = {
						credentials: { 
						    timestamp: '{$pp_timestamp}',
						    apiKey: '{$pp_apiKey}',
						    signature: '{$pp_signature}'
						},
						productValues: '{$pp_options}',
						apiKey: '{$pp_apiKey}'
					};
					PPADMIN.readyFncs.push('init', 'fetchDesigns');
					if (typeof PPADMIN.start !== 'undefined') PPADMIN.start();
				});
		</script>
	</div>
</div>