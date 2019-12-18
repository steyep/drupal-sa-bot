#!/bin/bash -x

last_post=${WORKSPACE}/timestamp
projects=${WORKSPACE}/projects
test -d $projects || mkdir -pv $project
repositories=()
arguments=()
# Indicates if we should only display messages we haven't seen before.
show_unread_only=1

function fetch_all_projects() {
  # Ensure all of the repositories have been cloned to our Project workspace.
  for repo in $@; do
    project_dir="${projects}/$(basename $repo)"
    test -d $project_dir ||
      git clone -b master --single-branch ${stash_clone_uri}/${repo}.git ${project_dir}
  done

  # Pull in the latest changes from all project repositories.
  find $projects -mindepth 1 -maxdepth 1 -type d -exec git --git-dir={}/.git --work-tree={} pull origin master \;
  return $?
}

function add_repository() {
  local repository="${1//.git}"
  echo "$repository" | grep '/' -q || {
    echo "Repositories must include project: \${PROJECT_NAME}/${repository}";
    return 1;
  }
  repositories+=("$repository");
  return 0;
}

function add_repositories() {
  for repo in ${1//,/ }; do
    add_repository "$repo"
  done
}

function get_projects() {
  local project_args=''
  for repo in ${repositories[@]}; do
    project_args+="$(basename $repo),"
  done
  echo ${project_args%,}
}

function get_security_advisory_notices() {
  [[ "$show_unread_only" == "0" && -f $last_post ]] && rm $last_post
  # Ensure all composer dependencies are install
  if [ ! -d ${WORKSPACE}/vendor ] && [ -f ${WORKSPACE}/composer.json ]; then
    composer install
  fi
  php $WORKSPACE/sa-bot.php --project-directory $projects --last-reported-log $last_post --projects $(get_projects) $@
}

while (( $# )); do
	case "$1" in
		--repos=*) add_repositories ${1#*=}; shift;;
		--repo*) add_repositories "$2"; shift 2;;
		--unread-only) show_unread_only="$2"; shift 2;;
    *) arguments+=("$1"); shift;;
	esac
done

fetch_all_projects "${repositories[@]}" && get_security_advisory_notices "${arguments[@]}"
exit $?
