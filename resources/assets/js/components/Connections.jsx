import React, { useState } from 'react'
import NavMenu from '../components/NavMenu'
import { Container } from "../components/ui/container";
import ConnectionCard  from './ConnectionCard.jsx'
import Connected from './Connected.jsx';
import UnverifiedState from './UnverifiedState.jsx';
import NotConnected from './NotConnected.jsx';
import ConnectionFailed from './ConnectionFailed.jsx';
import Welcome from './Welcome.jsx';

const Connections = () => {
  const [isStarted, setIsStarted] = useState(false);
  
  // Get status from localized data
  const verificationStatus = window.sureFeedbackAdmin?.verification_status || 'unverified';
  const connectionStatus = window.sureFeedbackAdmin?.connection_status || 'not_connected';

  // If setup is started, show welcome/setup component
  if (isStarted) {
    return <Welcome />;
  }

  // Determine which component to show based on status
  const renderContent = () => {
    // If not connected, show NotConnected component
    if (connectionStatus === 'not_connected') {
      return <NotConnected setIsStarted={setIsStarted} />;
    }
    
    // If connected, check verification status
    if (verificationStatus === 'verified') {
      return <Connected />;
    } else if (verificationStatus === 'failed') {
      return <ConnectionFailed />;
    } else {
      return <UnverifiedState />;
    }
  };

  return (
     <div id="surefeedback-dashboard-app" className="surefeedback-styles">
      <NavMenu />
      <div>
        <Container
                    align="stretch"
                    className="p-0 flex-col lg:flex-row box-border"
                    containerType="flex"
                    direction="row"
                    gap="sm"
                    justify="start"
                    style={{
                        width: "100%",
                    }}
                >
                    <Container.Item
                        className="p-0 w-full"
                        shrink={1}
                    >
                      {renderContent()}
                    </Container.Item>
                </Container>
      </div>
    </div>
  )
}

export default Connections
