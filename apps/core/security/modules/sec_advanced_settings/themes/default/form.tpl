<table width="100%" border="0" cellspacing="0" cellpadding="4" align="center">
    <tr class="letra12">
        {if $mode eq 'input'}
        <td align="left">
            <input class="button" type="submit" name="update_advanced_security_settings" value="{$SAVE}">&nbsp;&nbsp;
        </td>
        {/if}
    </tr>
</table>
<table class="tabForm" style="font-size: 16px;" width="100%" >
    <tr>
	<td  width="50%" valign='top'>
	    <table>
		<tr class="letra12">
		    <td align="left"><b style ="color:#E35332; font-weigth:bold;font-size:13px;font-family:'Lucida Console';">{$subtittle1}</b></td>
		</tr>
        <tr class="letra12">
            <td align="left" ><b>{$status_anonymous_sip.LABEL}:</b></td>
            <td align="left" ><input type="hidden" name="oldstatus_anonymous_sip" id="oldstatus_anonymous_sip" value="{if $value_anonymous_sip}1{else}0{/if}" /><input type="checkbox" name="status_anonymous_sip" id="status_anonymous_sip" {if $value_anonymous_sip}checked="checked"{/if} /></td>
        </tr>
	    </table>
	</td>
    </tr>
</table>
