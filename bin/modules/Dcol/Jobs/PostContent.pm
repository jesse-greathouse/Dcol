#!/usr/bin/perl

package Dcol::Jobs::PostContent;

use strict;
use Cwd qw(getcwd abs_path);
use File::Basename;
use Getopt::Long;
use Exporter 'import';
our @EXPORT_OK = qw(post_content);

my $bin = abs_path(dirname(__FILE__) . '/../../../');
my $applicationRoot = abs_path(dirname($bin));
my $src = $applicationRoot . '/src';

1;

my $blog = '';
my $site = '';

GetOptions ("blog=s"    => \$blog,
            "site=s"    => \$site);

# ====================================
#    Subroutines below this point
# ====================================

# Controls the PHP artisan CLI to run jobs based on laravel commands.
sub post_content {
    my ($iterations) = @ARGV;

    local $ENV{PATH} = "$bin:$ENV{PATH}";
    chdir $src;
    
    my @postCmd = ('php');
    push @postCmd, 'artisan';
    push @postCmd, 'dcol:postcontent';

    if (defined $iterations) {
        push @postCmd, $iterations;
    }

    if ($blog ne "") {
        push @postCmd, "--blog=$blog";
    }

    if ($site ne "") {
        push @postCmd, "--site=$site";
    }
    
    system(@postCmd);
    command_result($?, $!, "Dcol postcontent command issued...", \@postCmd);
}

sub command_result {
    my ($exit, $err, $operation_str, @cmd) = @_;

    if ($exit == -1) {
        print "failed to execute: $err \n";
        exit $exit;
    }
    elsif ($exit & 127) {
        printf "child died with signal %d, %s coredump\n",
            ($exit & 127),  ($exit & 128) ? 'with' : 'without';
        exit $exit;
    }
    else {
        printf "$operation_str exited with value %d\n", $exit >> 8;
    }
}
