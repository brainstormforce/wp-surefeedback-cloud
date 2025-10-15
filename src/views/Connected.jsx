import React, { useState } from "react";
import { Button, Dialog } from "@bsf/force-ui";
import { __ } from "@wordpress/i18n";
import { CheckCircle, AlertTriangle, Loader2 } from "lucide-react";
import { disconnectSite } from "../helpers/auth";

const Connected = () => {
  const [isDisconnecting, setIsDisconnecting] = useState(false);
  const [disconnectStatus, setDisconnectStatus] = useState(null); // null, 'success', 'error'
  const [errorMessage, setErrorMessage] = useState('');
  const [isDialogOpen, setIsDialogOpen] = useState(false);

  const handleDisconnectClick = () => {
    if (isDisconnecting) return;
    setIsDialogOpen(true);
  };

  const confirmDisconnect = async () => {
    setIsDialogOpen(false);
    setIsDisconnecting(true);
    setDisconnectStatus(null);
    setErrorMessage('');

    try {
      const result = await disconnectSite();
      
      if (result.success) {
        setDisconnectStatus('success');
        
        // Reload the page after short delay
        setTimeout(() => {
          window.location.reload();
        }, 2000);
      } else {
        setDisconnectStatus('error');
        setErrorMessage(result.error || __('Failed to disconnect site. Please try again.', 'surefeedback'));
      }
    } catch (error) {
      console.error('Disconnect error:', error);
      setDisconnectStatus('error');
      setErrorMessage(__('Network error occurred. Please try again.', 'surefeedback'));
    } finally {
      setIsDisconnecting(false);
    }
  };

  const handleGoToDashboard = () => {
    const appUrl = window.sureFeedbackAdmin?.connection?.app_url || 'http://localhost:3000';
    window.open(`${appUrl}/sites`, '_blank');
  };

  return (
    <div className="flex justify-center items-start min-h-screen">
      <div className="bg-white shadow-md rounded-2xl p-8 max-w-lg w-full text-center">
        <div className="text-2xl mb-1">🎉</div>
        <div className="flex flex-col items-center justify-center">
          <h2 className="text-xl font-semibold m-0 text-gray-800">
            {__("Website Connected Successfully!", "surefeedback")}
          </h2>
          <p className="text-gray-500 mb-3 text-sm w-80 text-center flex items-center">
            {__(
              "Your site is now linked with SureFeedback. Start gathering client feedback without friction.",
              "surefeedback"
            )}
          </p>
        </div>

        <div 
          className="rounded-lg mb-6 px-4 py-3 mx-auto flex items-center text-center w-full justify-center" 
          style={{
            border: '2px solid #E5E7EB',
            paddingTop: '20px',
            paddingBottom: '20px',
            width: '340px',
            // marginRights: '10px',
            // marginTop: '16px',
            cursor: 'pointer',
            borderRadius: '10px',
            outline: 'none',
            boxShadow: 'none',
          }}
        >
          <div className="grid grid-cols-2 gap-y-3 text-left">
            <span className="font-medium">Connection Site:</span>
            <span className="text-gray-700">{window.sureFeedbackAdmin.connection.site_data.site_url}</span>
            <span className="font-medium">Status:</span>
            <span className="flex items-center gap-1 text-green-600 bg-green-100 px-2 py-0.5 rounded-full text-xs font-medium w-fit">
              <CheckCircle size={14} />
              Active
            </span>
          </div>
        </div>

        {/* Disconnect Status Feedback */}
        {disconnectStatus && (
          <div className={`mb-4 p-3 rounded-lg text-center ${
            disconnectStatus === 'success' 
              ? 'bg-green-50 border border-green-200 text-green-700' 
              : 'bg-red-50 border border-red-200 text-red-700'
          }`}>
            {disconnectStatus === 'success' ? (
              <div className="flex items-center justify-center gap-2">
                <CheckCircle size={16} />
                {__("Site disconnected successfully! Redirecting...", "surefeedback")}
              </div>
            ) : (
              <div className="flex items-center justify-center gap-2">
                <AlertTriangle size={16} />
                <span>{errorMessage}</span>
              </div>
            )}
          </div>
        )}

        <div className="flex justify-center gap-4">
          <Button variant="ghost"  style={{
            border: '2px solid #D1D5DB',
            cursor: 'pointer',
            borderRadius: '10px',
            outline: 'none',
            boxShadow: 'none',
          }} 
          onClick={handleGoToDashboard}>
            {__("Go to Dashboard", "surefeedback")}
          </Button>
          <Button
            variant="ghost"
            style={{
            border: '2px solid #D62626',
            cursor: 'pointer',
            borderRadius: '10px',
            outline: 'none',
            boxShadow: 'none',
          }} 
            className="!bg-white !text-red-600 !border rounded-lg !border-red-600 hover:!bg-red-100"
            onClick={handleDisconnectClick}
            disabled={isDisconnecting}
          >
            {isDisconnecting ? (
              <div className="flex items-center gap-2">
                <Loader2 size={16} className="animate-spin" />
                {__("Disconnecting...", "surefeedback")}
              </div>
            ) : (
              __("Disconnect", "surefeedback")
            )}
          </Button>
        </div>
      </div>
      
      <Dialog
        design="simple"
        open={isDialogOpen}
        setOpen={setIsDialogOpen}
      >
        <Dialog.Backdrop />
        <Dialog.Panel>
          <Dialog.Header>
            <div className="flex items-center justify-between">
              <Dialog.Title>
                {__("Disconnect Site", "surefeedback")}
              </Dialog.Title>
            </div>
            <Dialog.Description>
              {__("Are you sure you want to disconnect this site from SureFeedback? This will deactivate the widget and clear all connection data.", "surefeedback")}
            </Dialog.Description>
          </Dialog.Header>
          <Dialog.Footer>
            <Button onClick={confirmDisconnect}>
              {__("Yes, Disconnect", "surefeedback")}
            </Button>
            <Button
              variant="outline"
              onClick={() => setIsDialogOpen(false)}
            >
              {__("Cancel", "surefeedback")}
            </Button>
          </Dialog.Footer>
        </Dialog.Panel>
      </Dialog>
    </div>
  );
};

export default Connected;