<?php
declare(strict_types=1);
\OCP\Util::addStyle('leiterrunde', 'leiterrunde');
\OCP\Util::addScript('leiterrunde_finanzen', 'settings');
?>
<div class="section lr" id="lr-finanzen-settings">
	<h2><?php p($l->t('Finances – IMAP & expenses')); ?></h2>
	<div id="lr-fin-form"></div>
	<button class="lr-btn" id="lr-fin-save"><?php p($l->t('Save')); ?></button>
	<button class="lr-btn secondary" id="lr-fin-test"><?php p($l->t('Test connection')); ?></button>
	<span id="lr-fin-msg"></span>
</div>
