#!/usr/bin/env bash
echo "##### post-receive hook #####"
read oldrev newrev refname
echo "Push triggered update to revision $newrev ($refname)"

CMD_CD="cd $(readlink -nf "$PWD/..")"
CMD_FETCH="env -i git fetch"
CMD_COMPOSER="sudo -u www-data composer install --no-dev 2>&1"
CMD_NPM="sudo -u www-data pnpm install --frozen-lockfile"
CMD_BUILD="sudo -u www-data nice pnpm run build"
CMD_LARAVEL_OPTIMIZE="sudo -u www-data php artisan optimize"
CMD_MIGRATE="sudo -u www-data php artisan migrate --force"
CMD_REDIS="sudo -u www-data php artisan app:clear-redis"

echo "$ $CMD_CD"
eval ${CMD_CD}
echo "$ $CMD_FETCH"
eval ${CMD_FETCH}
echo "$ $CMD_COMPOSER"
eval ${CMD_COMPOSER}
FRONTEND_CHANGED=$(git diff --name-only "$oldrev" "$newrev" | grep -E '^(resources/(js|sass|icons)/|package\.json|pnpm-lock\.yaml|pnpm-workspace\.yaml|webpack\.mix\.js|tsconfig\.json|\.babelrc\.js)')
if [ -n "$FRONTEND_CHANGED" ]; then
  echo "Frontend files changed, running install + build"
  echo "$ $CMD_NPM"
  eval ${CMD_NPM}
  echo "$ $CMD_BUILD"
  eval ${CMD_BUILD}
else
  echo "No frontend files changed, skipping install + build"
fi
echo "$ $CMD_LARAVEL_OPTIMIZE"
eval ${CMD_LARAVEL_OPTIMIZE}
echo "$ $CMD_MIGRATE"
eval ${CMD_MIGRATE}
echo "$ $CMD_REDIS"
eval ${CMD_REDIS}

echo "##### end post-receive hook #####"
