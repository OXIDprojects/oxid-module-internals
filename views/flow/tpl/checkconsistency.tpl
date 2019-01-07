<html>

<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache">
    <meta http-equiv="Pragma" content="no-cache">
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
</head>

<body>
<link rel="stylesheet" href="[{$oViewConf->getModuleUrl('moduleinternals','/out/module-internals.css')}]">
[{oxscript include=$oViewConf->getModuleUrl('moduleinternals','out/module-internals.js')}]
[{foreach from=$aModules key=Nr item=Module}]
    <button class="accordion [{if $Module.hasIssue}]active[{/if}]">[{$Module.title}]</button>
    <div class="checks panel">

        [{include file="items.tpl" title="AC_MI_VERSION" items=$Module.aVersions}]

        [{include file="items.tpl" title="AC_MI_CONTROLLER" items=$Module.aControllers}]

        [{include file="items.tpl" title="AC_MI_EXTEND" items=$Module.aExtended}]

        [{include file="items.tpl" title="AC_MI_FILES" items=$Module.aFiles}]

        [{if $Module.aBlocks|@count > 0}]
        <div style="position: relative;">
            <h3>[{oxmultilang ident="AC_MI_BLOCKS"}]</h3>
            <table class="box blocks">
                <tr><th>Active</th><th>Template</th><th>Blockname</th><th>File</th></tr>
                [{foreach from=$Module.aBlocks item=i}]
                <tr>
                    <td style="width:20px;" class="[{if $i.active}]active[{/if}]">&nbsp;</td>
                    <td>[{$i.template}]</td>
                    <td><span class="state [{$i.b_state}]">[{$i.block}]</span></td>
                    <td>
                        <div>
                            <span class="state [{$i.state}]">[{$i.file}]</span>
                        </div>
                    </td>
                </tr>
                [{/foreach}]
            </table>
            <br>
        </div>
        [{/if}]

        [{include file="items.tpl" title="AC_MI_TEMPLATES" items=$Module.aTemplates}]

        [{include file="items.tpl" title="AC_MI_SETTINGS" items=$Module.aSettings}]

        [{include file="items.tpl" title="AC_MI_EVENTS" items=$Module.aEvents}]

    </div>
    [{/foreach}]

<div class="actions">
    <b>[{oxmultilang ident="AC_LEGEND"}] : </b>
    <span class="state sok">[{oxmultilang ident="AC_STATE_OK"}]</span> <i>[{oxmultilang ident="AC_STATE_OK"}]</i>
    <span class="state sfatalm">[{oxmultilang ident="AC_STATE_FM"}]</span> <i>[{oxmultilang ident="AC_STATE_FM_LABEL"}]</i>
    <span class="state sfatals">[{oxmultilang ident="AC_STATE_FS"}]</span> <i>[{oxmultilang ident="AC_STATE_FS_LABEL"}]</i>
</div>
[{oxscript}]
</body>
</html>