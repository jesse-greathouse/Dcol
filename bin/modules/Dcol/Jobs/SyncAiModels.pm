#!/usr/bin/perl

package Dcol::Jobs::SyncAiModels;

use strict;
use Cwd qw(getcwd abs_path);
use File::Basename;
use Getopt::Long;
use Exporter 'import';
our @EXPORT_OK = qw(sync_ai_models);

my $bin = abs_path(dirname(__FILE__) . '/../../../');
my $applicationRoot = abs_path(dirname($bin));
my $src = $applicationRoot . '/src';

1;

# ====================================
#    Subroutines below this point
# ====================================

# Controls the PHP artisan CLI to run jobs based on laravel commands.
sub sync_ai_models {
    local $ENV{PATH} = "$bin:$ENV{PATH}";
    chdir $src;
    
    my @postCmd = ('php');
    push @postCmd, 'artisan';
    push @postCmd, 'dcol:finetunedmodel:sync';

    system(@postCmd);
    command_result($?, $!, "Dcol finetunedmodel sync command issued...", \@postCmd);
}

sub command_result {
    my ($exit, $err, $operation_str, @cmd) = @_;

    if ($exit == -1) {
        print "$operation_str failed to execute: $err \n";
        exit $exit;
    }
    elsif ($exit & 127) {
        printf "$operation_str child died with signal %d, %s coredump\n",
            ($exit & 127),  ($exit & 128) ? 'with' : 'without';
        exit $exit;
    }
}
