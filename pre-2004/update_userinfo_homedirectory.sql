--
-- update_userinfo_homedirectory.sql
--
-- Quick one-time script to convert/migrate users' homedirectories.
--
-- Author: Rob Howe
--

declare cursor c1 is
select rowid,homedirectory from dbtab.userinfo where homedirectory like
'/web/%' ;
str varchar2(50);
str1 varchar2(100);
begin
for i in c1
loop
   str := substr(i.homedirectory,1,14);
   str1 := '/home/proj1/users' || substr(i.homedirectory,5,length(i.homedirectory));
   --dbms_output.put_line(i.homedirectory);
   --dbms_output.put_line(str1);
   update isun.userinfo set homedirectory = str1
    where rowid = i.rowid;
   --if ( str = '/web') then
           --dbms_output.put_line(str);
   --end if;
end loop;
end;
