#!/usr/bin/perl

package Dcol::Configure;
use strict;
use File::Basename;
use Cwd qw(getcwd abs_path);
use Exporter 'import';
use Data::Dumper;
use Scalar::Util qw(looks_like_number);
use Term::Prompt;
use Term::Prompt qw(termwrap);
use Term::ANSIScreen qw(cls);
use lib(dirname(abs_path(__FILE__))  . "/../modules");
use Dcol::Config qw(get_configuration save_configuration);
use Dcol::Utility qw(splash);

our @EXPORT_OK = qw(configure);

my $os = get_operating_system();
my $osModule = 'Dcol::Configure::' . $os;
eval "use $osModule qw(
    get_user_input
)";
warn $@ if $@; # handle exception

my $user = $ENV{'LOGNAME'} || $ENV{'USER'} || getpwuid($<);
my $bin = abs_path(dirname(__FILE__) . '/../../');
my $applicationRoot = abs_path(dirname($bin));
my $etc = $applicationRoot . '/etc';
my $opt = $applicationRoot . '/opt';
my $src = $applicationRoot . '/src';
my $web = $applicationRoot . '/web';
my $passwordMatchAttempts = 0;
my $adminPasswordConfirm = '';
my %cfg = get_configuration();

1;

# ====================================
#    Subroutines below this point
# ====================================

# Trim the whitespace from a string.
sub  trim { my $s = shift; $s =~ s/^\s+|\s+$//g; return $s };

# Returns string associated with operating system.
sub get_operating_system {
    return 'Ubuntu';
}

# Performs the install routine.
sub configure {
    cls();
    splash();

    print (''."\n");
    print ('================================================================='."\n");
    print (' This will create your app\'s run script'."\n");
    print ('================================================================='."\n");
    print (''."\n");

    request_user_input();

    save_configuration(%cfg);
}

# Runs the user through a series of setup config questions.
# Confirms the answers.
# Returns Hash Table
sub request_user_input {
    # Save the input variables to the configuration.
    $cfg{'meta'}{'app_name'} = '';

    # APP_NAME
    if ($cfg{'meta'}{'app_name'} eq '') {
        input_app_name();
    }
}

sub input_app_name {
    $cfg{'meta'}{'app_name'} = prompt('x', 'Profile name of this app:', '', '');
}
