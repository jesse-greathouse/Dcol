#!/usr/bin/perl

package Dcol::Jobs::Crawl;
use strict;
use Cwd qw(getcwd abs_path);
use File::Basename;
use Exporter 'import';
our @EXPORT_OK = qw(crawl);

my $bin = abs_path(dirname(__FILE__) . '/../../../');
my $applicationRoot = abs_path(dirname($bin));
my $src = $applicationRoot . '/src';

1;

# ====================================
#    Subroutines below this point
# ====================================

# Controls the PHP artisan CLI to run jobs based on laravel commands.
sub crawl {
    my ($iterations) = @ARGV;
    local $ENV{PATH} = "$bin:$ENV{PATH}";
    chdir $src;
    
    my @crawlCmd = ('php');
    push @crawlCmd, 'artisan';
    push @crawlCmd, 'dcol:crawl';
    if (defined $iterations) {
        push @crawlCmd, $iterations;
    }
    system(@crawlCmd);
    command_result($?, $!, "Dcol crawl command issued...", \@crawlCmd);
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
