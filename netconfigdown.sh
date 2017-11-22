#!/bin/sh

# Usage:  $0 <file-to-process> <type>
# Type:  tunnel, ip, tunroute, ethroute
# File data fields requirements for "type: Comma separated values
# tunnel - name,type,remote,local,linkaddr
# ip - iface,ipaddress/cidr 
# tunroute - tunnel,iprange/cidr
# ethroute - iface,iprange/cidr 

if [ "$#" -ne 2 ]; then echo -e "Usage: $0 <file-to-process> <type>"; exit 1;fi

filename="$1"
case "$2" in
    tunnel)
        if [ -e "$filename" ]
            then
                echo -e "....Processing File: $filename...\n"
                for line in `cat $filename`
                    do
                        name=`echo $line |cut -d, -f1`
                        type=`echo $line |cut -d, -f2`
                        remote=`echo $line |cut -d, -f3`
                        local=`echo $line |cut -d, -f4` 
                        linkaddr=`echo $line |cut -d, -f5`

                        echo "### Deleting Tunnel $name ###"
                        ip tunnel del $name
                        #echo -e "ip tunnel del $name\t\tDone!"
                    done 
            else 
                echo -e "File $filename not found...Exiting!\n"
            fi
    ;;
    ip)
        if [ -e "$filename" ]
            then
                echo -e "\n....Processing File: $filename..."
                echo "UnBinding IPs"
                for line in `cat $filename`
                    do
                        bind_to_dev=`echo $line |cut -d, -f1`
                        ipaddr=`echo $line |cut -d, -f2`
                        #echo -e "ip addr del $ipaddr dev $bind_to_dev\t\tDone!"
                        ip addr del $ipaddr dev $bind_to_dev
                    done
            else 
                echo -e "File $filename not found...Exiting!\n"
            fi
    ;;
    tunroute)
         if [ -e "$filename" ]
            then
                echo -e "\n....Processing File: $filename..."
                echo "Deleting routes through tunnels"
                for line in `cat $filename`
                    do
                        tunid=`echo $line |cut -d, -f1`
                        iprange=`echo $line |cut -d, -f2`
                        #echo -e "ip rule del from $iprange table $tunid\t\tDone!"
                        ip rule del from $iprange table $tunid
                    done
            else 
                echo -e "File $filename not found...Exiting!\n"
            fi
    ;;
    ethroute)
        if [ -e "$filename" ]
            then
                echo -e "\n....Processing File: $filename..."
                echo "Deleting routes through default gw"
                for line in `cat $filename`
                    do
                        ifaceid=`echo $line |cut -d, -f1`
                        iprange=`echo $line |cut -d, -f2`
                        #echo -e "ip route del $iprange dev $ifaceid\t\tDone!"
                        ip route del $iprange dev $ifaceid
                    done
            else 
                echo -e "File $filename not found...Exiting!\n"
            fi
        ;;
    *)
        echo "Invalid Type!"
        echo "Usage: $0 <file-to-process> <type>"
        echo "Options:  tunnel, tunroute, ethroute, ip"
        ;;
esac
