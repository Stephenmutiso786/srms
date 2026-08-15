# VirtualBox Notes

This is a practical VM path you can run from Kali using `VBoxManage`.

## Example flow

1. Create a VM
2. Attach an ISO
3. Install the guest OS
4. Install PHP and MySQL inside the guest
5. Copy SRMS into the guest
6. Snapshot the VM
7. Export or clone it for transfer

## Helpful commands

```bash
VBoxManage list vms
VBoxManage list runningvms
VBoxManage snapshot "<VM Name>" take "SRMS Ready"
```

