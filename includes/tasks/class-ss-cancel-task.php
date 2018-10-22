<?php
namespace Simply_Static_Github_Sync;

class Cancel_Task extends Task {

	/**
	 * @var string
	 */
	protected static $task_name = 'cancel';

	public function perform() {
		$this->save_status_message( __( 'Cancelling job', 'simply-static-github-sync' ) );

		$wrapup_task = new Wrapup_Task();
		$wrapup_task->perform();

		return true;
	}

}
