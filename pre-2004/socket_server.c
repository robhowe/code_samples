/*
 * socket_server.c
 *
 * Program to demonstrate the usage of client/server
 * socket usage.  The client sends a mesgId to the server,
 * which replies with the corresponding text string.
 *
 * When executed, this application automatically starts-up
 * it's own server process (rather than having a separate
 * .c file and executable to keep track of).
 * To manually start-up the server separately, simply
 * append "SERVER_ONLY" to the command line (without the mesgId arg),
 * and then also append "CLIENT_ONLY" to the command line when
 * starting any client processes.
 *
 * Compile:  cc -o socket_server socket_server.c -lnsl -lsocket
 *
 * Usage:  socket_server <mesgId> [CLIENT_ONLY|SERVER_ONLY]
 *			 where mesgId is an integer
 *
 * Rev. History: 02/23/2000 Rob Howe - created
 */
/*
 * Note - more documentation & robust error-checking should
 *        be added for a production-quality application.
 */

#include <stdio.h>
#include <errno.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/signal.h>

/* Uncomment the line below to print extra debugging info: */
/* #define DEBUG */

#define NUM_MESGS     3
#define MAX_MESG_LEN  256

const char *mesgs[NUM_MESGS] = { "You've chosen message #1!",
        	               "Message #2, as requested...",
		       "<Insert flashy message-3 text here>" };


int main (int argc, char* argv[])
{
  int    retval;
  int	 svrPid = 0;
  int    wait_stat;
  int    srv_sockfd;     /* file descriptor of server's socket */
  int    accept_sockfd;  /* file descriptor of server's socket */
  int    cli_sockfd;     /* file descriptor of client's socket */
  char   command[MAX_MESG_LEN];
  int    mesgId = 0;
  char  *recvBuf;

  struct sockaddr  srv_sock;  /* name to bind to server socket */
  int    srv_socklen;         /* length of name (bytes) */
  struct sockaddr  srv_accept_sock;  /* name to accept */
  int    srv_accept_socklen;  /* length of name (bytes) */
  struct sockaddr  cli_sock;  /* name to bind to client socket */
  int    cli_socklen;         /* length of name (bytes) */


  if (argc < 2) {
    printf("Usage:  socket_server <mesgId> [CLIENT_ONLY|SERVER_ONLY]\n");
    printf("  where mesgId is 1-%d\n", NUM_MESGS);
    exit(-1);
  }


  /* Since both client & server use these socket names,
   * initialize them beforehand:
   */

  memset (&srv_sock, 0, sizeof(srv_sock));
  srv_sock.sa_family = AF_UNIX;
  strcpy(srv_sock.sa_data, "/tmp/testsrv");
  srv_socklen = strlen(srv_sock.sa_data) + sizeof(srv_sock.sa_family);
#ifdef DEBUG
  printf("srv_sock=%s, srv_socklen=%d.\n", srv_sock.sa_data, srv_socklen);
#endif

  memset (&cli_sock, 0, sizeof(cli_sock));
  cli_sock.sa_family = AF_UNIX;
  strcpy(cli_sock.sa_data, "/tmp/testcli");
  cli_socklen = strlen(cli_sock.sa_data) + sizeof(cli_sock.sa_family);
#ifdef DEBUG
  printf("cli_sock=%s, cli_socklen=%d.\n", cli_sock.sa_data, cli_socklen);
  fflush(stdout);
#endif

  if (strcmp("SERVER_ONLY", argv[1])) {
    /*
     * This is the initiation of the client:
     */

    mesgId = atoi(argv[1]);
#ifdef DEBUG
    printf("Client initiated...\n");
    printf("You requested mesgID %d.\n", mesgId);  fflush(stdout);
#endif
    if ((mesgId<1) || (mesgId>NUM_MESGS)) {
      printf("Error:  mesgId must be from 1 to %d.\n", NUM_MESGS);
      exit(-1);
    }

    /*
     * If not specified as CLIENT_ONLY, then the first thing
     * the client does is start-up the server process also.
     */
    if ((argc<3) || ((argc>2) && strcmp("CLIENT_ONLY", argv[2]))) {
      sprintf(command, "%s SERVER_ONLY", argv[0]);
#ifdef DEBUG
      printf("Auto startup: %s.\n", command);  fflush(stdout);
#endif
      /* Kick-off a separate server process: */
      if ((svrPid=fork())==0)
	system(command);
      else
	sleep(1);  /* give server time to get ready */
    }

    cli_sockfd = socket(AF_UNIX, SOCK_STREAM, 0);
#ifdef DEBUG
  printf("client created socket ok.\n");  fflush(stdout);
#endif

    /* Just in case it already exists: */
    sprintf(command, "rm %s 2>/dev/null", cli_sock.sa_data);
    system(command);

    retval = bind(cli_sockfd, &cli_sock, cli_socklen);
#ifdef DEBUG
  printf("client bind'ed ok.\n");  fflush(stdout);
#endif

    retval = connect(cli_sockfd, &srv_sock, srv_socklen);
#ifdef DEBUG
  printf("client connect'ed ok.\n");  fflush(stdout);
#endif

    /* sendto() could also be used instead of send() here: */
    /* retval = sendto(cli_sockfd, &mesgId, sizeof(mesgId),
       0, &srv_sock, srv_socklen);
     */
    retval = send(cli_sockfd, &mesgId, sizeof(mesgId), 0);
#ifdef DEBUG
    printf("  client sent %d bytes.\n", retval);  fflush(stdout);
#endif


    /* Now, receive the server's response string: */
    
    recvBuf = (char *) malloc(MAX_MESG_LEN);

    retval = recv(cli_sockfd, recvBuf, MAX_MESG_LEN, 0);
#ifdef DEBUG
    printf("client recv'd %d bytes: recvBuf=%s.\n", retval, recvBuf);  fflush(stdout);
#endif
    printf("%s\n", recvBuf);  fflush(stdout);

    retval = shutdown(cli_sockfd, 2);

    /* Cleanup: */
    sprintf(command, "rm %s 2>/dev/null", cli_sock.sa_data);
    system(command);

    /* If the server was auto-started, then also
     * auto-stop it:
     */
    if (svrPid) {
#ifdef DEBUG
      printf("Auto stop: server pid=%d.\n", svrPid);  fflush(stdout);
#endif
      sigsend(P_PID, svrPid, SIGKILL);
      /* Cleanup after server: */
      sprintf(command, "rm %s 2>/dev/null", srv_sock.sa_data);
      system(command);
    }

#ifdef DEBUG
    printf("Client done.\n");
#endif
    exit(0);


  } else {

    /*
     * This is the initiation of the server:
     */

    int  recvdMesgId = 0;

    printf("  Server initiated...\n");  fflush(stdout);

    srv_sockfd = socket(AF_UNIX, SOCK_STREAM, 0);
#ifdef DEBUG
    printf("  server created socket ok.\n");  fflush(stdout);
#endif

    /* Just in case it already exists: */
    sprintf(command, "rm %s 2>/dev/null", srv_sock.sa_data);
    system(command);

    retval = bind(srv_sockfd, &srv_sock, srv_socklen);
#ifdef DEBUG
    printf("  server bind'ed ok.\n");  fflush(stdout);
#endif

    while (1) { /* server runs until someone kills it */

      retval = listen(srv_sockfd, 3);
#ifdef DEBUG
      printf("  server listen'ed ok.\n");  fflush(stdout);
#endif

      srv_accept_socklen = sizeof(srv_accept_sock);

      accept_sockfd = accept(srv_sockfd, &srv_accept_sock, &srv_accept_socklen);
#ifdef DEBUG
      printf("  server accept'ed ok.\n");  fflush(stdout);
#endif

      retval = recv(accept_sockfd, &recvdMesgId, sizeof(recvdMesgId), 0);
#ifdef DEBUG
      printf("  server recv'd %d bytes: mesgId=%d.\n", retval, recvdMesgId);  fflush(stdout);
#endif

      if ((recvdMesgId<1) || (recvdMesgId>NUM_MESGS)) {
	printf("  Server Error:  mesgId=%d,  must be from 1 to %d.\n",
	       recvdMesgId, NUM_MESGS);

	retval = shutdown(accept_sockfd, 2);
	retval = shutdown(srv_sockfd, 2);

	/* Cleanup: */
	sprintf(command, "rm %s 2>/dev/null", srv_sock.sa_data);
	system(command);

	printf("  Server shut down.\n");
	exit(-1);
      }

      /* Send the requested corresponding message: */

      retval = send(accept_sockfd, mesgs[recvdMesgId-1],
		    strlen(mesgs[recvdMesgId-1]), 0);
#ifdef DEBUG
      printf("  server sent %d bytes=\"%s\".\n", retval, mesgs[recvdMesgId-1]);
#else
      printf("  Server sent mesg=\"%s\".\n", mesgs[recvdMesgId-1]);
#endif
      fflush(stdout);
    }  /* while forever */
  }

  exit(0);
}
