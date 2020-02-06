# Drupal Security Advisory Bot

![Screenshot](/screenshot.png?raw=true)

This can be deployed as a Jenkins job to monitor Drupal RSS feed for applicable security advisories. Upon discovery, a notification is posted to Slack with the option to create an issue in Jira for a hotfix.

Edit `src/Config.php` to include the projects to be monitored by adding them to the `$project_configuration` array. Project configurations should be an array containing a display name (`name`) and Jira project ID (`pid`) that is keyed by the name of the repository. Adding the `foo` repository with a Jira project ID of `123` would look like:
```
$project_configuration = [
  'foo' => ['name' => 'Foo Project', 'pid' => 123],
];
```

Configure Jenkins to run:
```
export stash_clone_uri='ssh://git@{your-stash}'; sh jenkins.sh \
  --slack-webhook https://hooks.slack.com/services/{your-web-hook} \
  --slack-channel '#your-slack-channel' \
  --jira-endpoint https://{your-jira-instance}/secure/CreateIssueDetails!init.jspa \
  --repos {project}/{repo},foo/bar \
  --unread-only 1
```