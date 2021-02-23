# Copyright 2021 Google LLC
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#      https://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

def _php_binary_impl(ctx):
    out_dir = ctx.actions.declare_directory("out")
    out_run_sh = ctx.actions.declare_file("run.sh")
    entry_point_relative = ctx.file.entry_point.path[len(ctx.attr.entry_point.label.workspace_root):].strip("/")
    # I don't understand why this is required:
    entry_point_relative = entry_point_relative[len("rules_php_gapic/"):]
    # copy uses -L to dereference symlinks, as the PHP __DIR__ constant is incorrect if the src file is symlinked
    # Ideally this cp would symlink all files except *.php files.
    cmd = """
cp -rL {install_path} {out_dir_path}
    """.format(
        install_path = ctx.file.php_composer_install.path,
        out_dir_path = out_dir.path,
    )
    ctx.actions.run_shell(
        inputs = [ctx.file.php_composer_install],
        outputs = [out_dir],
        command = cmd
    )
    # This changes directory to the PHP src install path before running php.
    # Without this the php code fails to execute.
    if ctx.attr.working_directory_flag_name:
        working_directory_flag = '--{name} "$WD"'.format(name=ctx.attr.working_directory_flag_name)
    else:
        working_directory_flag = ""
    run_sh = """#!/bin/bash
WD=$(pwd)
PHP="$WD/$(dirname $0)/run.sh.runfiles/$(basename $WD)/{php_short_path}"
cd "$(dirname $0)/run.sh.runfiles/$(basename $WD)/{out_short_path}/install"
"$PHP" -n './{entry_point}' {working_directory_flag}
    """.format(
        php_short_path = ctx.file.php.short_path,
        out_short_path = out_dir.short_path,
        entry_point = entry_point_relative,
        working_directory_flag = working_directory_flag,
    )
    ctx.actions.write(out_run_sh, run_sh, is_executable=True)
    return [DefaultInfo(
        files=depset([out_dir]),
        runfiles=ctx.runfiles([out_run_sh, out_dir, ctx.file.php]),
        executable=out_run_sh,
    )]

php_binary = rule(
    implementation = _php_binary_impl,
    attrs = {
        "php": attr.label(default=Label("@php//:bin/php"), allow_single_file=True, executable=True, cfg="host"),
        "php_composer_install": attr.label(allow_single_file=True),
        "entry_point": attr.label(allow_single_file=True),
        "working_directory_flag_name": attr.string(),
    },
    executable = True,
)
