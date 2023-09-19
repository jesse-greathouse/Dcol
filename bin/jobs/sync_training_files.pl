#!/usr/bin/perl

use strict;

use Cwd qw(getcwd abs_path);
use File::Basename;
use lib(dirname(abs_path(__FILE__))  . "/../modules");
use Dcol::Jobs::SyncTrainingFiles qw(sync_training_files);

sync_training_files();
