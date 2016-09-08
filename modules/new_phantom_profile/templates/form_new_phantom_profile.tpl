{if $success}

<p>New phantom candidate created. DCCID: {$candID} PSCID: {$PSCID}<br />
<a href="{$baseurl}/{$candID}/">Access this phantom candidate</a><br />
<a href="{$baseurl}/new_phantom_profile/">Recruit another phantom candidate</a></p>

{else}

<br />
<form method="post" name="new_phantom_profile" id="new_phantom_profile" class="form-inline">

    {foreach from=$form.errors item=error}
    <div class="col-sm-12">
        <label class="error col-sm-12">{$error}</label>
    </div>
    {/foreach}

        <div class="form-group col-sm-12">
                <label class="col-sm-2">{$form.PhantomTypes.label}</label>
                <div class="col-sm-10">{$form.PhantomTypes.html}</div>
        </div>
        <br><br>

        <div class="form-group col-sm-12">
	                <label class="col-sm-2">{$form.PhantomNames.label}</label>
                <div class="col-sm-10">{$form.PhantomNames.html}</div>
        </div>
        <br><br>

    {if $form.PSCID.html != ""}
	<div class="form-group col-sm-12">
		<label class="col-sm-2">{$form.PSCID.label}</label>
		<div class="col-sm-10">{$form.PSCID.html}</div>
	</div>
	<br><br>
    {/if}

    {if $form.ProjectID.html != ""}
    <div class="form-group col-sm-12">
        <label class="col-sm-2">{$form.ProjectID.label}</label>
        <div class="col-sm-10">{$form.ProjectID.html}</div>
    </div>
    <br><br>
    {/if}

	<div class="form-group col-sm-12">
		<div class="col-sm-12"><input class="btn btn-primary col-sm-offset-2 col-sm-2" name="fire_away" value="Create" type="submit" /></div>
	</div>
</table>
{$form.hidden}
</form>

{/if}
