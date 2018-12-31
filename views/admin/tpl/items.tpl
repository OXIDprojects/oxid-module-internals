[{if $items|@count > 0}]
<div style="position: relative;">
    <h3>[{oxmultilang ident=$title}]</h3>
    <table>
        [{foreach from=$items key=key item=item}]
        <tr>
            <td><span class="state [{$item.key_state}]">[{$key}]</span></td>
            <td>
                <span class="state [{$item.state}]">[{$item.data}]</span>
            </td>
        </tr>
        [{/foreach}]
    </table>
    <br>
</div>
[{/if}]