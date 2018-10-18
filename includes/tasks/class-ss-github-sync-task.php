<?php
namespace Simply_Static;

use Cz\Git\GitRepository;

class Github_sync_Task extends Task
{

    /**
     * @var string
     */
    protected static $task_name = 'github_sync';

    /**
     * @var GitRepository
     */
    private $repository;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->gitUser = $this->options->get('github_user');
        $this->accessToken = $this->options->get('github_access_token');
        $this->repositoryPath = $this->options->get('github_repository');
        $this->branchName = $this->options->get('github_branch');
        $this->localDir = $this->options->get('local_dir');
        $this->filesToExclude = $this->options->get('github_files_to_exclude');
    }

    /**
     * Fetch and save pages for the static archive
     * @return boolean|WP_Error true if done, false if not done, WP_Error if error
     */
    public function perform()
    {
        $this->save_status_message(__("Git initialize. ", 'simply-static-github-sync'));
        $this->initialize();
        
        // Github sync
        $this->save_status_message(__("Github syncing.  ", 'simply-static-github-sync'));
        $this->sync();

        // remove temporary directory
        $this->delTempDir($this->tmpDir);
        $this->delTempDir($this->localDir);

        $this->save_status_message(__("Github sync complete! ", 'simply-static-github-sync'));
        return true;
    }

    /**
     * Github Sync
     */
    private function initialize()
    {
        try {
            $this->setTempDir();
            // Cloning of repository into own directory
            $repository_uri = 'https://' . $this->gitUser . ':' . $this->accessToken . '@github.com/' . $this->repositoryPath;
            $this->repository = GitRepository::cloneRepository($repository_uri, $this->tmpDir);
            // Checkout
            $this->repository->checkout($this->branchName);

            // Setting git config
            $this->repository->execute(array('config', '--local', 'user.name', $this->options->get('github_user')));
            $this->repository->execute(array('config', '--local', 'user.email', $this->options->get('github_email')));
        } catch (Exception $e) {
            $this->save_status_message(__($e));
            return false;
        }
    }

    /**
     * Create and set the temporary directory location.
     */
    private function setTempDir()
    {
        $temp_dir = tempnam(sys_get_temp_dir(), 'github_');
        if (file_exists($temp_dir)) {
            unlink($temp_dir);
        }
        mkdir($temp_dir);
        $this->tmpDir = $temp_dir;
    }

    /**
     * Delete the temporary directory.
     */
    private function delTempDir($dir)
    {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->delTempDir("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    /**
     * Copy the static file in the temporary directory.
     */
    private function copyStaticFileToTmpDir($formDir, $toDir)
    {
        $dir = opendir($formDir);
        if (!file_exists($toDir)) {
            @mkdir($toDir);
        }
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($formDir . '/' . $file)) {
                    $this->copyStaticFileToTmpDir($formDir . '/' . $file, $toDir . '/' . $file);
                } else {
                    if (file_exists($formDir . '/' . $file) && 'php' !== substr($file, strrpos($file, '.') + 1)) {
                        copy($formDir . '/' . $file, $toDir . '/' . $file);
                    }
                }
            }
        }
        closedir($dir);
    }

    /**
     * Synchronize the generated file.
     */
    private function sync()
    {
        $date = date(DATE_ATOM, time());

        // Remove
        $this->repository->execute(array('rm', '-r', '--ignore-unmatch', './*'));

        // Files not to be synchronized.
        $this->repository->execute(array('reset', 'HEAD', implode(" ", $this->filesToExclude)));
        $this->repository->execute(array('checkout', implode(" ", $this->filesToExclude)));

        // Copy
        $this->copyStaticFileToTmpDir($this->localDir, $this->tmpDir);

        if ($this->repository->hasChanges()) {
            // Commit
            $this->repository->addAllChanges();
            $this->repository->commit('Simply Static Github Sync Plugin:' . $date);
            // Push
            $this->repository->push('origin', array($this->branchName));
        }
    }
}
