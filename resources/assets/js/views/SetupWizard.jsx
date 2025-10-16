import React, { useEffect } from 'react'
import OB from './index'

const SetupWizard = () => {
  useEffect(() => {
    const body = document.body
    body.classList.add('surefeedback-onboarding-fullscreen')

    return () => {
      body.classList.remove('surefeedback-onboarding-fullscreen')
    }
  }, [])

  return (
    <>
      <OB />
    </>
  )
}

export default SetupWizard