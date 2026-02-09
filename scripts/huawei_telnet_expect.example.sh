#!/usr/bin/expect -f

set timeout 10

set ip "your_huawei_device_ip"
set user "your_username"
set pass "your_password"
set port [lindex $argv 0]

spawn telnet $ip

expect "Username:"
send "$user\r"

expect "Password:"
send "$pass\r"

expect ">"

send "screen-length 0 temporary\r"
expect ">"

send "display transceiver interface $port verbose\r"
expect ">"

set output $expect_out(buffer)

send "quit\r"
expect eof

set tx [exec echo "$output" | grep "TX Power(dBM)" | awk -F: "{print \$2}"]
set rx [exec echo "$output" | grep "RX Power(dBM)" | awk -F: "{print \$2}"]
set temp [exec echo "$output" | grep "Temperature" | awk -F: "{print \$2}"]

set tx [string trim $tx]
set rx [string trim $rx]
set temp [string trim $temp]

puts "TX=$tx"
puts "RX=$rx"
puts "TEMP=$temp"