[{include file="headitem.tpl" box="box" title="GENERAL_ADMIN_TITLE"|oxmultilangassign}]

<form name="transfer" id="transfer" action="[{ $oViewConf->getSelfLink() }]" method="post">
    [{ $oViewConf->getHiddenSid() }]
    <input type="hidden" name="oxid" value="[{ $oxid }]">
    <input type="hidden" name="cl" value="module_internals_state">
    <input type="hidden" name="fnc" value="" id="fnc">
    <input type="hidden" name="data" value="" id="data">
    <input type="hidden" name="editlanguage" value="[{ $editlanguage }]">
</form>

<style>
    span.state.sok {color:green;}
    span.state.sfatalm {color:red;text-decoration:line-through;}
    span.state.sfatals {color:red;text-decoration:underline;}
    button.fix {position: absolute;top:0; right: 0;}
    .actions i {margin-right:20px;display:inline_blocks;}
</style>

<script>
    function module_internals_fix(fnc, data = '') {
        document.getElementById('fnc').value = fnc;
        document.getElementById('data').value = data;
        document.getElementById('transfer').submit();
    }
</script>

[{include file="items.tpl" title="AC_MI_VERSION" items=$aVersions}]

[{include file="items.tpl" title="AC_MI_CONTROLLER" items=$aControllers}]

[{include file="items.tpl" title="AC_MI_EXTEND" items=$aExtended}]

[{include file="items.tpl" title="AC_MI_FILES" items=$aFiles}]

[{if $aBlocks|@count > 0}]
<div style="position: relative;">
    <h3>[{oxmultilang ident="AC_MI_BLOCKS"}]</h3>
    <table class="box">
        <tr><th>Active</th><th>Template</th><th>Blockname</th><th>File</th></tr>
        [{foreach from=$aBlocks item=i}]
            <tr>
                <td style="width:20px;" class="[{if $i.active}]active[{/if}]"></td>
                <td><span class="state [{$i.t_state}]">[{$i.template}]</span></td>
                <td><span class="state [{$i.b_state}]">[{$i.block}]</span></td>
                <td>
                    <div>
                        <span class="state [{$i.state}]">[{$i.file}]</span>
                    </div>
                </td>
                <td>
                    <button onclick="module_internals_fix('block','[{$i.id}]')">
                        [{if $i.active}]
                            [{oxmultilang ident="AC_MI_DEACTIVATEBTN"}]
                        [{else}]
                            [{oxmultilang ident="AC_MI_ACTIVATEBTN"}]
                        [{/if}]
                    </button>
                </td>

            </tr>
        [{/foreach}]
    </table>
    <br>
</div>
[{/if}]

[{include file="items.tpl" title="AC_MI_TEMPLATES" items=$aTemplates}]

[{include file="items.tpl" title="AC_MI_SETTINGS" items=$aSettings}]

[{include file="items.tpl" title="AC_MI_EVENTS" items=$aEvents}]

[{include file="bottomitem.tpl"}]

</div>
<div class="actions">
    <b>[{oxmultilang ident="AC_LEGEND"}] : </b>
    <span class="state sok">[{oxmultilang ident="AC_STATE_OK"}]</span> <i>&nbsp;</i>
    <span class="state sfatalm">[{oxmultilang ident="AC_STATE_FM"}]</span> <i>[{oxmultilang ident="AC_STATE_FM_LABEL"}]</i>
    <span class="state sfatals">[{oxmultilang ident="AC_STATE_FS"}]</span> <i>[{oxmultilang ident="AC_STATE_FS_LABEL"}]</i>
</div>
