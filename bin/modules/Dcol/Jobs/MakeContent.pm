#!/usr/bin/perl

package Dcol::Jobs::MakeContent;

use strict;
use Cwd qw(getcwd abs_path);
use File::Basename;
use Getopt::Long;
use Exporter 'import';
our @EXPORT_OK = qw(make_content);

my $bin = abs_path(dirname(__FILE__) . '/../../../');
my $applicationRoot = abs_path(dirname($bin));
my $src = $applicationRoot . '/src';

1;

my $uri = '';
my $regress = 0;

GetOptions ("uri=s"     => \$uri,
            "regress"   => \$regress);

# ====================================
#    Subroutines below this point
# ====================================

# Controls the PHP artisan CLI to run jobs based on laravel commands.
sub make_content {
    my ($iterations) = @ARGV;

    local $ENV{PATH} = "$bin:$ENV{PATH}";
    chdir $src;
    
    my @makeCmd = ('php');
    push @makeCmd, 'artisan';
    push @makeCmd, 'dcol:makecontent';

    if (defined $iterations) {
        push @makeCmd, $iterations;
    }

    if ($uri ne "") {
        push @makeCmd, "--uri=$uri";
    }

    if (!$regress) {
        push @makeCmd, "--regress";
    }
    
    system(@makeCmd);
    command_result($?, $!, "Dcol makecontent command issued...", \@makeCmd);
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
